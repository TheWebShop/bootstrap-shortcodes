<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<link rel="stylesheet" href="../../css/bootstrap.css" />
<link rel="stylesheet" href="../../css/admin.css" />
<script type="text/javascript" src="../jquery.js"></script>
<script type="text/javascript" src="../bootstrap.js"></script>
<script type="text/javascript">
$(function() {
	jQuery('#insert').click(function() {
        var size = jQuery('body').find('select[name=size]').val();
        var type = jQuery('body').find('select[name=type]').val();
        var value = jQuery('body').find('input[name=value]').val();
        var link = jQuery('body').find('input[name=link]').val();
        top.tinymce.activeEditor.insertContent('[bs_button size="'+size.toLowerCase()+'" type="'+type.toLowerCase()+'" value="'+value+'" href="'+link+'"]');
        top.tinymce.activeEditor.windowManager.close();
	});
});
</script>
</head>
<body>
	<div class="container">
		<form role="form">
			<div class="form-group">
				<label for="size">Size</label>
					<select class="form-control" id="size" name="size">
						<option value="xs">Mini</option>
						<option value="sm">Small</option>
						<option value="md" selected>Normal</option>
						<option value="lg">Large</option>
					</select>
				<label for="type">Types</label>
				<select class="form-control" id="type" name="type">
					<option value="Default"> Default</option>
					<option value="Primary"> Primary</option>
					<option value="Success"> Success</option>
					<option value="Info" selected> Info</option>
					<option value="Warning"> Warning</option>
					<option value="Danger"> Danger</option>
					<option value="Link"> Link</option>
				</select>
				<label for="value">Value</label>
				<input type="text" class="form-control" id="value" name="value" placeholder="Value" onclick="this.select()" />
				<label for="link">Link</label>
				<input type="text" class="form-control" id="link" name="link" placeholder="#" onclick="this.select()" />
			</div>
			<button type="submit" id="insert" class="btn btn-primary">Insert</button>
		</form>
	</div>
</body>
</html>