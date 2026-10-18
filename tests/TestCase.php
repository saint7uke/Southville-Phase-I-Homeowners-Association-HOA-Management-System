<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests which do not explicitly fake a disk must still never write to
        // the application's real private documents or public uploads.
        $root = storage_path('framework/testing/audit-process-'.getmypid());
        config()->set('filesystems.disks.local.root', $root.'/private');
        config()->set('filesystems.disks.public.root', $root.'/public');
    }
}
