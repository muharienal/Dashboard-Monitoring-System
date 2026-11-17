<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Tidak memakai Mockery untuk output console (dependensi dev diminimalkan). */
    public $mockConsoleOutput = false;
    //
}
