
<div class="element-set">

  <?php if ($showElementSetHeadings): ?>
    <h2><?php echo html_escape(__($setName)); ?></h2>
  <?php endif; ?>


    <div id="<?php echo text_to_id(html_escape("$setName $elementName")); ?>" class="element">
    <h3><?php echo html_escape(__($elementName)); ?></h3>

    <div class="element-text">

      <table style="width:300px">
        <?php while($row = $statement->fetch()):?>
          <tr>
            <td> test </td>
            <td> test </td>
            <td> test </td>
            <td> test </td>
          </tr>
        <? endwhile; ?>
      </table

    </div>
  
</div><!-- end element-set -->
