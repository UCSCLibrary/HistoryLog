<?php
if ($declaration):
    echo '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL; ?>
<office:document-meta xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:meta="urn:oasis:names:tc:opendocument:xmlns:meta:1.0" xmlns:ooo="http://openoffice.org/2004/office" xmlns:grddl="http://www.w3.org/2003/g/data-view#" office:version="1.2">
<?php endif; ?>
 <office:meta>
  <meta:initial-creator><?php echo $user->name; ?></meta:initial-creator>
  <meta:creation-date><?php echo $dateTime; ?></meta:creation-date>
  <meta:document-statistic meta:table-count="<?php echo count($tableNames); ?>" meta:cell-count="<?php echo $cells; ?>" meta:object-count="0"/>
  <meta:generator><?php echo $generator; ?></meta:generator>
 </office:meta>
<?php if ($declaration): ?>
</office:document-meta>
<?php endif;
