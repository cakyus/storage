<?php

include('Storage.php');

$storage = new Storage('notes');
$note = new stdClass;

$note->content = 'Hello World !';

// add data into storage
$key = $storage->put($note);

// retrieve data from storage
$note = $storage->get($key);

// updating data
$note->content = 'Hello World 2 !';
$storage->set($note, $key);

// delete data
$storage->del($key);
