<?php

include('config.php');
include('Storage.php');

$storage = new Storage('notes');
$note = new stdClass;

$id = uniqid();
$note->id = $id;
$note->content = 'Hello World !';

// add data into storage
$storage->put($note);

// retrieve data from storage
$note = $storage->get($id);

// updating data
$note->content = 'Hello World 2 !';
$storage->set($note);

// delete data
$storage->del($note);
