<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    /**
     * Get the client/site from the current host.
     * Stub implementation for testing.
     */
    public static function getClientFromHost()
    {
        // Return a mock site for testing with teams() method
        $site = new class {
            public $id = 1;
            public $site_name = 'Test Site';

            public function teams()
            {
                return collect([
                    new class {
                        public $id = 1;
                    }
                ]);
            }
        };

        return $site;
    }
}
