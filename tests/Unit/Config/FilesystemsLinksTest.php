<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class FilesystemsLinksTest extends TestCase
{
    /**
     * The `storage:link` symlink must expose only the PUBLIC disk root
     * (storage/app/public), never the whole `local` disk root
     * (storage/app/), which holds private files such as
     * storage/app/cheques. Linking the wider directory would serve
     * private files unauthenticated from the public docroot.
     */
    public function test_storage_link_maps_to_public_disk_root_only(): void
    {
        $links = config('filesystems.links');

        $this->assertSame(
            storage_path('app/public'),
            $links[public_path('storage')],
            'The storage:link target must be storage/app/public, not storage/app/.'
        );
    }

    public function test_local_disk_root_is_not_under_the_public_path(): void
    {
        $localRoot = config('filesystems.disks.local.root');
        $publicPath = public_path();

        $this->assertNotEquals($publicPath, $localRoot);
        $this->assertStringStartsNotWith(
            $publicPath,
            $localRoot,
            'The private `local` disk root must never live under public_path().'
        );
    }
}
