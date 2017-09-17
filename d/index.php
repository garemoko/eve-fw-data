<?php

redirect('https://discord.gg/n7Nm4aB');

function redirect($url, $statusCode = 303)
{
   header('Location: ' . $url, true, $statusCode);
   die();
}