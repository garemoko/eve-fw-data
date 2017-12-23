<h2>I'm sorry <?=$character->data->character->name?>, I'm afraid I can't do that.</h2>

<div class="help-section">
  <?php 
    $allianceName = '[NO ALLIANCE]';
    if (isset($character->data->alliance->name)){
      $allianceName = $character->data->alliance->name;
    }

    echo (
      'You are not allowed to access this site because '
      . $character->data->character->name . ', '
      . $character->data->corp->name . ', and '
      . $allianceName
      . ' are not on the allowed list.'
    );
  ?>
</div>