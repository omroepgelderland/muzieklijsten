<?php

require_once __DIR__.'/include/include.php';

?>
<!DOCTYPE html>
<html lang="nl">
<head>
</head>
<body>

<iframe id="iframeHelper" width="100%" src="resultaten.php?lijst=<?php echo $_GET["lijst"]; ?>&admin=1" frameborder="0"></iframe>

<script>
setTimeout('$("#iframeHelper").height( $("#iframeHelper").contents().find("html").height() );', 500);
</script>


</body>
</html>
