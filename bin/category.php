#!/usr/bin/php
<?php

require __DIR__ . '/../vendor/autoload.php';

$cli = new splitbrain\TheBankster\CLI\CategoryCLI();
$cli->run();
