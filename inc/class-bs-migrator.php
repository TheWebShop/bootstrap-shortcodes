<?php
/**
 * Converter: bs_* shortcode text into block markup or static HTML.
 *
 * Rules it keeps:
 *  - everything that is not one of our shortcodes is passed through byte for byte
 *    (HTML, other plugins' shortcodes, escaped [[bs_x]], unrecognised bs_ tags);
 *  - an occurrence it cannot convert safely (unbalanced open/close) is left in
 *    place and reported in `skipped`;
 *  - anything it drops or approximates is reported in `notes`;
 *  - the CSS/JS the output still relies on is reported in `dependencies`.
 *
 * Modes:  blocks (core block markup), html (static HTML), auto (blocks when the
 *         post already has blocks, otherwise html).
 * Styles: inline (self-contained: inline styles, inline SVG icons, wells and
 *         alerts as Custom HTML blocks) or classes (keep Bootstrap class names
 *         only; needs Bootstrap 3 CSS from the theme or the legacy-assets companion).
 */
class BS_Migrator {

	const VERSION = 1;

	private static $options;
	private static $skipped;
	private static $notes;
	private static $deps;
	private static $converted;
	private static $unbalanced;
	private static $tab_titles;
	private static $section_counter;
	private static $row_depth = 0;
	/** True while converting inside a block whose HTML only allows inline content (paragraph, heading ...). */
	private static $inline_only = false;

	/** Components that render block-level HTML (a div, p or details). */
	private static $block_level = array( 'bs_row', 'bs_col', 'bs_well', 'bs_notification', 'bs_lead', 'bs_collapse', 'bs_citem', 'bs_tabs', 'bs_thead', 'bs_tab', 'bs_dropdown', 'bs_tcontents', 'bs_tcontent' );

	/** Blocks whose saved HTML may hold block-level markup converted in place. */
	private static $block_containers = array( 'core/freeform', 'core/html' );

	public static function convert( $content, $options = array() ) {
		$options = wp_parse_args( $options, array( 'mode' => 'auto', 'style' => 'inline' ) );
		if ( 'auto' === $options['mode'] ) {
			$options['mode'] = ( is_string( $content ) && has_blocks( $content ) ) ? 'blocks' : 'html';
		}
		self::$options    = $options;
		self::$skipped    = array();
		self::$notes      = array();
		self::$deps       = array();
		self::$converted  = 0;
		self::$tab_titles = array();
		self::$section_counter = 0;
		self::$row_depth       = 0;
		self::$inline_only     = false;

		$result = array(
			'content'      => $content,
			'converted'    => 0,
			'skipped'      => array(),
			'notes'        => array(),
			'dependencies' => array(),
			'mode'         => $options['mode'],
			'style'        => $options['style'],
			'version'      => self::VERSION,
		);
		if ( ! is_string( $content ) || false === strpos( $content, '[bs_' ) ) {
			return $result;
		}

		$scan             = BS_Scanner::scan_text( $content );
		self::$unbalanced = $scan['unbalanced'];

		if ( has_blocks( $content ) ) {
			// A block document: convert inside the blocks that hold shortcodes
			// (Shortcode, Classic, Paragraph, Custom HTML ...) without breaking
			// their structure. In html mode the replacements are plain HTML.
			$result['content'] = serialize_blocks( self::convert_blocks( parse_blocks( $content ) ) );
		} else {
			$result['content'] = self::process( $content );
			if ( 'blocks' === $options['mode'] ) {
				// Loose text between blocks becomes paragraphs (or a Classic block), as the editor would.
				$result['content'] = self::inner_blocks( $result['content'] );
			}
		}
		$result['converted']    = self::$converted;
		$result['skipped']      = self::$skipped;
		$result['notes']        = array_values( array_unique( self::$notes ) );
		$result['dependencies'] = array_values( array_unique( self::$deps ) );
		return $result;
	}

	/* ------------------------------------------------------------------ */
	/* Block documents                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Walk parsed blocks. Shortcode blocks and standalone-shortcode paragraphs
	 * are replaced by the converted blocks; Classic, Custom HTML and other
	 * blocks get their inner HTML converted as static HTML; inner blocks recurse.
	 */
	private static function convert_blocks( $blocks ) {
		$out = array();
		foreach ( $blocks as $block ) {
			$html = isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';
			$has  = false !== strpos( $html, '[bs_' );
			if ( null === $block['blockName'] ) {
				if ( $has ) {
					$block = self::replacement_block( $html );
				}
				$out[] = $block;
				continue;
			}
			if ( 'core/shortcode' === $block['blockName'] && $has ) {
				$out[] = self::replacement_block( $html );
				continue;
			}
			if ( 'core/paragraph' === $block['blockName'] && $has && preg_match( '/^\s*<p[^>]*>\s*(\[bs_[\s\S]*\])\s*<\/p>\s*$/', $html, $m ) && self::is_single_shortcode( $m[1] ) ) {
				$out[] = self::replacement_block( $m[1] );
				continue;
			}
			if ( $has ) {
				// Components inside this block's own HTML (paragraph, heading, list,
				// classic, custom HTML ...) are converted in place as static HTML. Only
				// Classic and Custom HTML blocks may take block-level markup; inside a
				// paragraph or heading a <div> would break the block (and the page), so
				// block-level components there are left in place and listed.
				$saved_mode            = self::$options['mode'];
				self::$options['mode'] = 'html';
				self::$inline_only     = ! in_array( $block['blockName'], self::$block_containers, true );
				$block['innerHTML']    = self::process( $html );
				$counted               = self::$converted;
				$skipped               = self::$skipped;
				foreach ( $block['innerContent'] as $i => $chunk ) {
					if ( is_string( $chunk ) && false !== strpos( $chunk, '[bs_' ) ) {
						$block['innerContent'][ $i ] = self::process( $chunk );
					}
				}
				// innerContent repeats innerHTML: count and list each occurrence once.
				self::$converted       = $counted;
				self::$skipped         = $skipped;
				self::$inline_only     = false;
				self::$options['mode'] = $saved_mode;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::convert_blocks( $block['innerBlocks'] );
			}
			$out[] = $block;
		}
		return $out;
	}

	/** One of our shortcodes (possibly enclosing), and nothing else. */
	private static function is_single_shortcode( $text ) {
		if ( ! preg_match_all( '/' . get_shortcode_regex( BS_Scanner::tags() ) . '/', $text, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return false;
		}
		return 1 === count( $m ) && 0 === $m[0][0][1] && strlen( $m[0][0][0] ) === strlen( $text );
	}

	/** A whole block (Shortcode, Classic text, lone-shortcode paragraph) replaced by its conversion. */
	private static function replacement_block( $html ) {
		$converted = self::process( $html );
		return self::raw_block( self::blocks() ? self::inner_blocks( $converted ) : $converted );
	}

	private static function raw_block( $html ) {
		$html = "\n" . trim( $html ) . "\n";
		return array( 'blockName' => null, 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => $html, 'innerContent' => array( $html ) );
	}

	/* ------------------------------------------------------------------ */
	/* Walking                                                             */
	/* ------------------------------------------------------------------ */

	private static function process( $text ) {
		if ( false === strpos( $text, '[bs_' ) ) {
			return $text;
		}
		$pattern = '/' . get_shortcode_regex( BS_Scanner::tags() ) . '/';
		$out     = '';
		$last    = 0;
		if ( ! preg_match_all( $pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			return $text;
		}
		foreach ( $matches as $m ) {
			$start = $m[0][1];
			$whole = $m[0][0];
			$out  .= substr( $text, $last, $start - $last );
			$last  = $start + strlen( $whole );

			if ( '[' === $m[1][0] && ']' === $m[6][0] ) {
				$out .= $whole; // escaped: literal text by design
				continue;
			}
			$tag = $m[2][0];
			if ( in_array( $tag, self::$unbalanced, true ) ) {
				self::$skipped[] = array( 'tag' => $tag, 'reason' => 'unbalanced', 'offset' => $start );
				$out .= $whole;
				continue;
			}
			if ( self::$inline_only && in_array( $tag, self::$block_level, true ) ) {
				self::$skipped[] = array( 'tag' => $tag, 'reason' => 'inline-context', 'offset' => $start );
				$out .= $whole;
				continue;
			}
			$attrs   = shortcode_parse_atts( $m[3][0] );
			$attrs   = is_array( $attrs ) ? $attrs : array();
			$content = isset( $m[5][0] ) ? $m[5][0] : '';
			// The editor's own line-break markers ("<br class="nc" />", or without the
			// space before TinyMCE normalises it) were never meant to render.
			$content = preg_replace( '/<br class="nc"\s*\/?>/', '', $content );
			self::note_unknown_attrs( $tag, $attrs );

			$before     = substr( $text, 0, $start );
			$after      = substr( $text, $last );
			$standalone = (bool) preg_match( '/(^|\n)[ \t]*$/', $before ) && (bool) preg_match( '/^[ \t]*(\n|$)/', $after );

			$replacement = self::convert_one( $tag, $attrs, $content, $standalone );
			if ( null === $replacement ) {
				$out .= $whole;
				continue;
			}
			self::$converted++;
			$out .= $replacement;
		}
		$out .= substr( $text, $last );
		return $out;
	}

	private static function note_unknown_attrs( $tag, $attrs ) {
		$known = BS_Scanner::$known_attrs[ $tag ];
		foreach ( $attrs as $k => $v ) {
			if ( is_string( $k ) && ! in_array( $k, $known, true ) ) {
				/* translators: 1: attribute, 2: shortcode */
				self::$notes[] = sprintf( 'Attribute "%1$s" on [%2$s] was never used by the plugin and is dropped.', $k, $tag );
			}
		}
	}

	private static function blocks() {
		return 'blocks' === self::$options['mode'];
	}

	private static function inline_style() {
		return 'inline' === self::$options['style'];
	}

	private static function attr( $value ) {
		return esc_attr( (string) $value );
	}

	private static function convert_one( $tag, $attrs, $content, $standalone ) {
		switch ( $tag ) {
			case 'bs_row':
				return self::row( $attrs, $content );
			case 'bs_col':
				return self::col( $attrs, $content );
			case 'bs_well':
				return self::well( $attrs, $content );
			case 'bs_notification':
				return self::alert( $attrs, $content );
			case 'bs_lead':
				return self::lead( $content );
			case 'bs_button':
				return self::button( $attrs, $standalone );
			case 'bs_label':
				return self::label( $attrs, $content );
			case 'bs_icon':
				return self::icon( $attrs );
			case 'bs_tooltip':
				return self::tooltip( $attrs, $content );
			case 'bs_collapse':
				return self::collapse( $attrs, $content );
			case 'bs_citem':
				return self::citem( $attrs, $content, '' );
			case 'bs_tabs':
				return self::tabs( $content );
			case 'bs_thead':
			case 'bs_dropdown':
			case 'bs_tab':
				// Only meaningful inside [bs_tabs]; stray ones have nothing to show.
				self::$notes[] = sprintf( 'A stray [%s] outside [bs_tabs] was removed (it rendered an empty tab strip).', $tag );
				return '';
			case 'bs_tcontents':
				return self::process( $content );
			case 'bs_tcontent':
				return self::tcontent( $attrs, $content );
		}
		return null;
	}

	/* ------------------------------------------------------------------ */
	/* Inner content helpers                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Turn already-processed inner content into inner blocks: existing blocks
	 * pass through, plain text becomes paragraphs, anything else becomes a
	 * Classic block (which keeps wpautop behaviour for mixed HTML).
	 */
	private static function inner_blocks( $html ) {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}
		$parts = array();
		foreach ( parse_blocks( $html ) as $block ) {
			if ( null !== $block['blockName'] ) {
				$parts[] = trim( serialize_block( $block ) );
				continue;
			}
			$chunk = trim( $block['innerHTML'] );
			if ( '' === $chunk ) {
				continue;
			}
			if ( $chunk === wp_strip_all_tags( $chunk ) ) {
				foreach ( preg_split( '/\n\s*\n/', $chunk ) as $para ) {
					$para = trim( $para );
					if ( '' !== $para ) {
						$parts[] = "<!-- wp:paragraph -->\n<p>" . str_replace( "\n", "<br>\n", $para ) . "</p>\n<!-- /wp:paragraph -->";
					}
				}
				continue;
			}
			if ( self::is_simple_inline_html( $chunk ) ) {
				$parts[] = "<!-- wp:paragraph -->\n<p>" . $chunk . "</p>\n<!-- /wp:paragraph -->";
				continue;
			}
			$parts[] = "<!-- wp:freeform -->\n" . $chunk . "\n<!-- /wp:freeform -->";
		}
		return implode( "\n\n", $parts );
	}

	/** One line of text with only inline elements (span, a, svg, i, strong ...). */
	private static function is_simple_inline_html( $chunk ) {
		if ( false !== strpos( $chunk, "\n" ) ) {
			return false;
		}
		$stripped = preg_replace( '#<(/?)(a|abbr|b|strong|i|em|span|svg|path|code|small|sub|sup|br)\b[^>]*>#i', '', $chunk );
		return false === strpos( $stripped, '<' );
	}

	private static function wrap_block( $name, $attrs, $html ) {
		$json = $attrs ? ' ' . wp_json_encode( $attrs, JSON_UNESCAPED_SLASHES ) : '';
		return "<!-- wp:{$name}{$json} -->\n{$html}\n<!-- /wp:{$name} -->";
	}

	/** Static HTML keeps classic behaviour: wpautop runs later, as it always did. */
	private static function inner_html( $html ) {
		return self::process( $html );
	}

	/* ------------------------------------------------------------------ */
	/* Components                                                          */
	/* ------------------------------------------------------------------ */

	private static function row( $attrs, $content ) {
		// The handler used `class` instead of "row" (shortcode_atts default), never in addition.
		$classes = isset( $attrs['class'] ) ? array_values( array_filter( preg_split( '/\s+/', trim( $attrs['class'] ) ) ) ) : array( 'row' );
		$extra   = array_values( array_diff( $classes, array( 'row' ) ) );
		if ( ! self::blocks() ) {
			self::$deps[] = 'bootstrap-css';
			return '<div class="' . self::attr( implode( ' ', $classes ) ) . '">' . self::inner_html( $content ) . '</div>';
		}
		self::$row_depth++;
		$inner = self::process( $content );
		self::$row_depth--;
		// Inner must be columns only; stray text between columns is kept as its own column.
		$columns = array();
		foreach ( parse_blocks( trim( $inner ) ) as $block ) {
			if ( 'core/column' === $block['blockName'] ) {
				$columns[] = trim( serialize_block( $block ) );
			} elseif ( '' !== trim( $block['innerHTML'] ) || null !== $block['blockName'] ) {
				$columns[] = self::wrap_block( 'column', array(), '<div class="wp-block-column">' . self::inner_blocks( null !== $block['blockName'] ? serialize_block( $block ) : $block['innerHTML'] ) . '</div>' );
				self::$notes[] = 'Content inside a [bs_row] that was not in a [bs_col] was placed in its own column.';
			}
		}
		$block_attrs = $extra ? array( 'className' => implode( ' ', $extra ) ) : array();
		$class       = 'wp-block-columns' . ( $extra ? ' ' . implode( ' ', $extra ) : '' );
		return self::wrap_block( 'columns', $block_attrs, '<div class="' . self::attr( $class ) . '">' . implode( '', $columns ) . '</div>' );
	}

	private static function col( $attrs, $content ) {
		$classes = isset( $attrs['class'] ) ? preg_split( '/\s+/', trim( $attrs['class'] ) ) : array( 'col-sm-1' );
		$classes = array_values( array_filter( $classes ) );
		if ( ! self::blocks() ) {
			self::$deps[] = 'bootstrap-css';
			return '<div class="' . self::attr( implode( ' ', $classes ) ) . '">' . self::inner_html( $content ) . '</div>';
		}
		$width = null;
		$extra = array();
		foreach ( $classes as $class ) {
			if ( preg_match( '/^col-(xs|sm|md|lg)-(\d{1,2})$/', $class, $mm ) ) {
				if ( null === $width ) {
					$width = (int) $mm[2];
				}
			} elseif ( preg_match( '/^col-(xs|sm|md|lg)-(offset|push|pull)-\d{1,2}$/', $class ) ) {
				self::$notes[] = sprintf( 'Grid %s class "%s" has no Columns-block equivalent and was dropped.', 'offset/push/pull', $class );
			} else {
				$extra[] = $class;
			}
		}
		$block_attrs = array();
		$style       = '';
		if ( $width && $width < 12 ) {
			$pct                  = rtrim( rtrim( number_format( $width / 12 * 100, 2, '.', '' ), '0' ), '.' ) . '%';
			$block_attrs['width'] = $pct;
			$style                = ' style="flex-basis:' . $pct . '"';
		}
		if ( $extra ) {
			$block_attrs['className'] = implode( ' ', $extra );
		}
		$class  = 'wp-block-column' . ( $extra ? ' ' . implode( ' ', $extra ) : '' );
		$inner  = self::inner_blocks( self::process( $content ) );
		$column = self::wrap_block( 'column', $block_attrs, '<div class="' . self::attr( $class ) . '"' . $style . '>' . $inner . '</div>' );
		if ( 0 === self::$row_depth ) {
			// A Column block is only valid inside Columns.
			self::$notes[] = 'A [bs_col] outside a [bs_row] was given its own Columns block.';
			return self::wrap_block( 'columns', array(), '<div class="wp-block-columns">' . $column . '</div>' );
		}
		return $column;
	}

	private static function well( $attrs, $content ) {
		$size  = isset( $attrs['size'] ) && in_array( $attrs['size'], array( 'sm', 'lg' ), true ) ? $attrs['size'] : '';
		$class = 'well' . ( $size ? ' well-' . $size : '' );
		$pad   = 'lg' === $size ? '24px' : ( 'sm' === $size ? '9px' : '19px' );
		$style = 'background-color:#f5f5f5;border:1px solid #e3e3e3;border-radius:' . ( 'lg' === $size ? '6px' : ( 'sm' === $size ? '3px' : '4px' ) ) . ';padding:' . $pad . ';margin-bottom:20px';
		return self::box( $class, $style, $content, '' );
	}

	private static function alert( $attrs, $content ) {
		$type   = isset( $attrs['type'] ) ? sanitize_html_class( $attrs['type'], 'unknown' ) : 'unknown';
		$colors = array(
			'success' => array( '#dff0d8', '#d6e9c6', '#3c763d' ),
			'info'    => array( '#d9edf7', '#bce8f1', '#31708f' ),
			'warning' => array( '#fcf8e3', '#faebcc', '#8a6d3b' ),
			'danger'  => array( '#f2dede', '#ebccd1', '#a94442' ),
		);
		$c     = isset( $colors[ $type ] ) ? $colors[ $type ] : array( '#f5f5f5', '#e3e3e3', '#333333' );
		$style = 'background-color:' . $c[0] . ';border:1px solid ' . $c[1] . ';color:' . $c[2] . ';border-radius:4px;padding:15px;margin-bottom:20px';
		if ( ! isset( $attrs['dismissible'] ) || 'true' === $attrs['dismissible'] ) {
			self::$notes[] = 'Alerts lose their dismiss (×) button: it needs Bootstrap JavaScript. The message itself is kept.';
		}
		return self::box( 'alert alert-' . $type, $style, $content, ' role="alert"' );
	}

	/** Shared by wells and alerts: a styled container. */
	private static function box( $class, $style, $content, $extra_attrs ) {
		if ( self::blocks() ) {
			if ( self::inline_style() ) {
				$inner = self::process( $content );
				$html  = '<div class="' . self::attr( $class ) . '"' . $extra_attrs . ' style="' . self::attr( $style ) . '">' . self::bake_paragraphs( $inner ) . '</div>';
				return self::wrap_block( 'html', array(), $html );
			}
			self::$deps[] = 'bootstrap-css';
			if ( '' !== $extra_attrs ) {
				// The Group block's save() writes no role attribute; keeping it would make the block invalid.
				self::$notes[] = 'The alert role is not kept: the Group block cannot store it. The message and colours are kept.';
			}
			return self::wrap_block( 'group', array( 'className' => $class ), '<div class="wp-block-group ' . self::attr( $class ) . '">' . self::inner_blocks( self::process( $content ) ) . '</div>' );
		}
		$inner = self::inner_html( $content );
		if ( self::inline_style() ) {
			return '<div class="' . self::attr( $class ) . '"' . $extra_attrs . ' style="' . self::attr( $style ) . '">' . $inner . '</div>';
		}
		self::$deps[] = 'bootstrap-css';
		return '<div class="' . self::attr( $class ) . '"' . $extra_attrs . '>' . $inner . '</div>';
	}

	/**
	 * Inside a Custom HTML block wpautop no longer runs, so bake the paragraphs
	 * classic content relied on. Block markup inside is rendered to HTML.
	 */
	private static function bake_paragraphs( $html ) {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}
		if ( has_blocks( $html ) ) {
			$html = do_blocks( $html );
		}
		return wpautop( $html );
	}

	private static function lead( $content ) {
		$inner = self::process( $content );
		if ( self::blocks() ) {
			if ( trim( $inner ) === wp_strip_all_tags( trim( $inner ) ) || self::is_simple_inline_html( trim( $inner ) ) ) {
				return self::wrap_block( 'paragraph', array( 'className' => 'lead', 'fontSize' => 'large' ), '<p class="lead has-large-font-size">' . trim( $inner ) . '</p>' );
			}
			return self::wrap_block( 'group', array( 'className' => 'lead', 'fontSize' => 'large' ), '<div class="wp-block-group lead has-large-font-size">' . self::inner_blocks( $inner ) . '</div>' );
		}
		$style = self::inline_style() ? ' style="font-size:1.3em;font-weight:300"' : '';
		if ( ! self::inline_style() ) {
			self::$deps[] = 'bootstrap-css';
		}
		$tag = ( trim( $inner ) === wp_strip_all_tags( trim( $inner ) ) || self::is_simple_inline_html( trim( $inner ) ) ) ? 'p' : 'div';
		return '<' . $tag . ' class="lead"' . $style . '>' . trim( $inner ) . '</' . $tag . '>';
	}

	private static function button( $attrs, $standalone ) {
		$a = shortcode_atts( array( 'size' => '', 'type' => 'default', 'value' => 'button', 'href' => '#', 'target' => '' ), $attrs );
		$target = strtolower( trim( $a['target'] ) );
		$target_attr = '';
		if ( in_array( $target, array( '_blank', '_self', '_parent', '_top' ), true ) ) {
			$target_attr = ' target="' . self::attr( $target ) . '"' . ( '_blank' === $target ? ' rel="noopener noreferrer"' : '' );
		}
		if ( self::blocks() && $standalone ) {
			$dropped = array();
			if ( 'default' !== $a['type'] && '' !== $a['type'] ) {
				$dropped[] = 'btn-' . $a['type'];
			}
			if ( '' !== $a['size'] && $a['size'] !== $a['type'] ) {
				$dropped[] = 'btn-' . $a['size'];
			}
			if ( $dropped ) {
				self::$notes[] = sprintf( 'Button style "%s" is not carried over: the Button block uses your theme\'s button style. Restyle it in the editor if the colour or size mattered.', implode( ' ', $dropped ) );
			}
			$link = '<a class="wp-block-button__link wp-element-button" href="' . esc_url( $a['href'] ) . '"' . $target_attr . '>' . esc_html( $a['value'] ) . '</a>';
			$inner = self::wrap_block( 'button', array(), '<div class="wp-block-button">' . $link . '</div>' );
			return self::wrap_block( 'buttons', array(), '<div class="wp-block-buttons">' . $inner . '</div>' );
		}
		// Inline (or html mode): identical to what the shortcode rendered.
		$size_class = ( $a['size'] && $a['size'] !== $a['type'] ) ? ' btn-' . self::attr( $a['size'] ) : '';
		if ( self::inline_style() ) {
			self::$deps[] = 'bootstrap-css';
			self::$notes[] = 'Inline buttons keep their Bootstrap "btn" classes: a button in the middle of a sentence has no block equivalent.';
		} else {
			self::$deps[] = 'bootstrap-css';
		}
		return '<a class="btn btn-' . self::attr( $a['type'] ) . $size_class . '" href="' . esc_url( $a['href'] ) . '"' . $target_attr . '>' . esc_html( $a['value'] ) . '</a>';
	}

	private static function label( $attrs, $content ) {
		$type   = isset( $attrs['type'] ) ? sanitize_html_class( $attrs['type'], 'default' ) : 'default';
		$colors = array( 'default' => '#777', 'primary' => '#337ab7', 'success' => '#5cb85c', 'info' => '#5bc0de', 'warning' => '#f0ad4e', 'danger' => '#d9534f' );
		$bg     = isset( $colors[ $type ] ) ? $colors[ $type ] : '#777';
		$inner  = self::process( $content );
		if ( self::inline_style() ) {
			return '<span class="label label-' . $type . '" style="display:inline-block;padding:.2em .6em .3em;font-size:75%;font-weight:700;line-height:1;color:#fff;background-color:' . $bg . ';border-radius:.25em;white-space:nowrap;vertical-align:baseline">' . $inner . '</span>';
		}
		self::$deps[] = 'bootstrap-css';
		return '<span class="label label-' . $type . '">' . $inner . '</span>';
	}

	private static function icon( $attrs ) {
		$name    = isset( $attrs['name'] ) ? trim( $attrs['name'] ) : 'default';
		$classes = preg_split( '/\s+/', $name );
		$glyph   = null;
		foreach ( $classes as $class ) {
			if ( preg_match( '/^glyphicon-([a-z0-9-]+)$/', $class, $mm ) ) {
				$glyph = $mm[1];
			}
		}
		$fallback = '<i class="' . self::attr( $name ) . '" aria-hidden="true"></i>';
		if ( ! $glyph && preg_match( '/(^|\s)icon-[a-z0-9-]+/', $name ) ) {
			// Bootstrap 2 style icon-* classes: a PNG sprite styled by the plugin's
			// shortcodes.css, which loads with the Bootstrap CSS (and the companion's).
			self::$deps[]  = 'bootstrap-css';
			self::$notes[] = sprintf( 'Icon "%s" uses the old icon-* sprite; its markup was kept and still needs the Bootstrap 3 CSS bundle (theme or companion).', $name );
			return $fallback;
		}
		if ( ! self::inline_style() || ! $glyph ) {
			self::$deps[] = 'glyphicons';
			return $fallback;
		}
		$path = BS_Glyphs::path( $glyph );
		if ( ! $path ) {
			self::$deps[] = 'glyphicons';
			self::$notes[] = sprintf( 'Icon "%s" is not in the bundled Glyphicons set; its markup was kept and still needs the font.', $glyph );
			return $fallback;
		}
		$extra = array_values( array_diff( $classes, array( 'glyphicon', 'glyphicon-' . $glyph ) ) );
		$class = 'bs-icon bs-icon-' . $glyph . ( $extra ? ' ' . implode( ' ', $extra ) : '' );
		return '<svg class="' . self::attr( $class ) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . (int) $path['adv'] . ' 1200" width="1em" height="1em" aria-hidden="true" focusable="false" style="display:inline-block;vertical-align:-.15em;fill:currentColor"><path transform="translate(0,960) scale(1,-1)" d="' . self::attr( $path['d'] ) . '"/></svg>';
	}

	private static function tooltip( $attrs, $content ) {
		$text = trim( $content );
		self::$notes[] = 'Tooltips become native browser tooltips (title attribute): no placement or trigger control, and no tooltip on touch devices. The text is shown inline as before.';
		$style = self::inline_style() ? ' style="text-decoration:underline dotted;cursor:help"' : '';
		return '<span class="bs-tooltip" title="' . self::attr( $text ) . '"' . $style . '>' . esc_html( $text ) . '</span>';
	}

	private static function collapse( $attrs, $content ) {
		$group = isset( $attrs['id'] ) ? sanitize_html_class( $attrs['id'] ) : '';
		$inner = self::process( $content );
		if ( self::blocks() ) {
			return trim( $inner );
		}
		return '<div class="bs-collapse"' . ( $group ? ' id="' . self::attr( $group ) . '"' : '' ) . '>' . $inner . '</div>';
	}

	private static function citem( $attrs, $content, $unused ) {
		// Bootstrap made items exclusive only through data-parent (the `parent`
		// attribute); without it each item toggled on its own, so no group name.
		$group = isset( $attrs['parent'] ) ? sanitize_html_class( ltrim( trim( $attrs['parent'] ), '#' ) ) : '';
		$id    = isset( $attrs['id'] ) ? sanitize_html_class( $attrs['id'] ) : '';
		$title = isset( $attrs['title'] ) ? $attrs['title'] : 'Collapse title';
		$open  = isset( $attrs['open'] ) && 'true' === $attrs['open'];
		return self::details( $title, $content, $open, $id, $group );
	}

	private static function details( $title, $content, $open, $id, $group ) {
		$title_html = esc_html( wp_strip_all_tags( $title ) );
		if ( self::blocks() ) {
			// Match what this WordPress's Details block can save, or the editor
			// flags the block as invalid: no block before 6.3, no `name` before 6.8.
			$type = WP_Block_Type_Registry::get_instance()->get_registered( 'core/details' );
			if ( ! $type ) {
				self::$notes[] = 'Collapsible sections are saved as Custom HTML blocks: this WordPress version has no Details block (it arrived in 6.3).';
				$html = '<details' . ( $group ? ' name="' . self::attr( $group ) . '"' : '' ) . ( $id ? ' id="' . self::attr( $id ) . '"' : '' ) . ( $open ? ' open' : '' ) . '><summary>' . $title_html . '</summary>' . self::bake_paragraphs( self::process( $content ) ) . '</details>';
				return self::wrap_block( 'html', array(), $html );
			}
			if ( $group && ! isset( $type->attributes['name'] ) ) {
				self::$notes[] = 'Collapse items no longer close each other when one opens: the Details block\'s grouping needs WordPress 6.8 or later. Re-migrate after updating WordPress to keep that behaviour.';
				$group = '';
			}
			$block_attrs = array();
			if ( $open ) {
				$block_attrs['showContent'] = true;
			}
			if ( $group ) {
				$block_attrs['name'] = $group;
			}
			$html = '<details class="wp-block-details"' . ( $group ? ' name="' . self::attr( $group ) . '"' : '' ) . ( $id ? ' id="' . self::attr( $id ) . '"' : '' ) . ( $open ? ' open' : '' ) . '><summary>' . $title_html . '</summary>' . self::inner_blocks( self::process( $content ) ) . '</details>';
			return self::wrap_block( 'details', $block_attrs, $html );
		}
		return '<details' . ( $group ? ' name="' . self::attr( $group ) . '"' : '' ) . ( $id ? ' id="' . self::attr( $id ) . '"' : '' ) . ( $open ? ' open' : '' ) . '><summary>' . $title_html . '</summary>' . self::inner_html( $content ) . '</details>';
	}

	/**
	 * Tabs: collect the tab strip (titles by href) first, then render each pane
	 * as a collapsible section titled by its tab. A disclosed semantic change.
	 */
	private static function tabs( $content ) {
		self::$notes[] = 'Tabs become collapsible sections (one <details> per tab, in order). Dropdown tabs are flattened as "Dropdown: Tab". On WordPress 7.1+ you can convert them to the Tabs block in the editor afterwards.';
		$titles = array();
		if ( preg_match_all( '/' . get_shortcode_regex( array( 'bs_thead' ) ) . '/', $content, $heads, PREG_SET_ORDER ) ) {
			foreach ( $heads as $head ) {
				self::collect_tab_titles( isset( $head[5] ) ? $head[5] : '', '', $titles );
			}
		}
		$previous         = self::$tab_titles;
		self::$tab_titles = $titles;
		self::$section_counter = 0;
		// Strip the head: its tabs are now titles. Convert the panes.
		$panes = preg_replace( '/' . get_shortcode_regex( array( 'bs_thead' ) ) . '/', '', $content );
		$panes = self::process( $panes );
		self::$tab_titles = $previous;
		if ( self::blocks() ) {
			return trim( $panes );
		}
		return '<div class="bs-tabs">' . trim( $panes ) . '</div>';
	}

	private static function collect_tab_titles( $head, $prefix, &$titles ) {
		if ( ! preg_match_all( '/' . get_shortcode_regex( array( 'bs_tab', 'bs_dropdown' ) ) . '/', $head, $items, PREG_SET_ORDER ) ) {
			return;
		}
		foreach ( $items as $item ) {
			$attrs = shortcode_parse_atts( $item[3] );
			$attrs = is_array( $attrs ) ? $attrs : array();
			if ( 'bs_dropdown' === $item[2] ) {
				$title = isset( $attrs['title'] ) ? $attrs['title'] : '';
				self::collect_tab_titles( isset( $item[5] ) ? $item[5] : '', $title ? $title . ': ' : '', $titles );
				continue;
			}
			$href = isset( $attrs['href'] ) ? ltrim( trim( $attrs['href'] ), '#' ) : '';
			if ( '' !== $href && ! isset( $titles[ $href ] ) ) {
				$titles[ $href ] = $prefix . ( isset( $attrs['title'] ) ? $attrs['title'] : $href );
			}
		}
	}

	private static function tcontent( $attrs, $content ) {
		$id   = isset( $attrs['id'] ) ? sanitize_html_class( $attrs['id'] ) : '';
		$open = isset( $attrs['class'] ) && 'active' === trim( $attrs['class'] );
		self::$section_counter++;
		$title = ( $id && isset( self::$tab_titles[ $id ] ) ) ? self::$tab_titles[ $id ] : sprintf( 'Section %d', self::$section_counter );
		return self::details( $title, $content, $open, $id, '' );
	}
}

/**
 * Glyphicons Halflings paths, read from the SVG font this plugin already ships
 * (the same glyphs the CSS maps to .glyphicon-* classes), so migrated icons
 * need no font or CSS at all.
 */
class BS_Glyphs {

	private static $map;

	public static function path( $name ) {
		self::load();
		return isset( self::$map[ $name ] ) ? self::$map[ $name ] : null;
	}

	public static function names() {
		self::load();
		return array_keys( self::$map );
	}

	private static function load() {
		if ( null !== self::$map ) {
			return;
		}
		self::$map = array();
		$dir  = dirname( BS_SHORTCODES_FILE );
		$css  = @file_get_contents( $dir . '/css/bootstrap.css' );
		$font = @file_get_contents( $dir . '/fonts/glyphicons-halflings-regular.svg' );
		if ( ! $css || ! $font ) {
			return;
		}
		// class -> codepoint
		$codes = array();
		if ( preg_match_all( '/\.glyphicon-([a-z0-9-]+):before\{content:"\\\\([0-9a-f]+)"\}/i', $css, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $mm ) {
				$codes[ $mm[1] ] = hexdec( $mm[2] );
			}
		}
		// codepoint -> path
		$paths = array();
		if ( preg_match_all( '/<glyph\s+([^>]*)\/>/', $font, $g, PREG_SET_ORDER ) ) {
			foreach ( $g as $gg ) {
				if ( ! preg_match( '/unicode="([^"]*)"/', $gg[1], $u ) || ! preg_match( '/\sd="([^"]*)"/', $gg[1], $d ) ) {
					continue;
				}
				$uni = html_entity_decode( $u[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$cp  = self::codepoint( $uni );
				if ( null === $cp ) {
					continue;
				}
				$adv = preg_match( '/horiz-adv-x="(\d+)"/', $gg[1], $a ) ? (int) $a[1] : 1200;
				$paths[ $cp ] = array( 'd' => $d[1], 'adv' => $adv );
			}
		}
		foreach ( $codes as $name => $cp ) {
			if ( isset( $paths[ $cp ] ) ) {
				self::$map[ $name ] = $paths[ $cp ];
			}
		}
	}

	private static function codepoint( $char ) {
		if ( '' === $char ) {
			return null;
		}
		$ord = function_exists( 'mb_ord' ) ? mb_ord( $char, 'UTF-8' ) : null;
		if ( null === $ord || false === $ord ) {
			$u = unpack( 'N', mb_convert_encoding( $char, 'UCS-4BE', 'UTF-8' ) );
			$ord = $u ? $u[1] : null;
		}
		return $ord;
	}
}
