<h2>I'm sorry <?=$character->data->character->name?>, I'm afraid I can't do that.</h2>

<div class="help-section">
  <?php 
    echo (
      'You are not allowed to access this site because '
      . $character->data->character->name . ', '
      . $character->data->corp->corporation_name . ', and '
      . $character->data->alliance->alliance_name 
      . ' are not on the allowed list.'
    );
  ?>
</div>