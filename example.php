<?php

include('Storage.php');

$storage = new Storage('notes');
$note = new stdClass;

$note->content = 'Hello World !';

// add data into storage
$id = $storage->put($note);
echo "Note-Id: ".$id."\n";

// retrieve data from storage
$note = $storage->get($id);

// updating data
$note->content = 'Hello World 2 !';
$storage->set($note, $id);

// delete data
$storage->del($id);
