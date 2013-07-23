<?php

include('config.php');
include('Storage.php');

$storage = new Storage('notes');
$note = new stdClass;

$note->content = 'Hello World !';

// add data into storage
$storage->put($note);
echo "Note-Id: ".$note->id."\n";

// retrieve data from storage
$note = $storage->get($note->id);

// updating data
$note->content = 'Hello World 2 !';
$storage->set($note);

// delete data
$storage->del($id);
