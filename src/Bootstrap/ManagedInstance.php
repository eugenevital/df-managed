<?php namespace DreamFactory\Managed\Bootstrap;

use DreamFactory\Library\Utility\Disk;
use DreamFactory\Managed\Providers\ClusterServiceProvider;
use Illuminate\Contracts\Foundation\Application;

class ManagedInstance
{
    //******************************************************************************
    //* Members
    //******************************************************************************

    /**
     * @param Application $app
     */
    public function bootstrap(Application $app)
    {
        if ('managed' != $app->environment()) {
            return;
        }

        $app->register(new ClusterServiceProvider($app));
        $_cluster = ClusterServiceProvider::service();

        $_vars = [
            'DF_CACHE_PREFIX'         => $_cluster->getCachePrefix(),
            'DF_CACHE_PATH'           => $_cluster->getCachePath(),
            'DF_MANAGED_SESSION_PATH' => Disk::path([$_cluster->getCacheRoot(), '.sessions'], true),
            'DF_MANAGED'              => true,
        ];

        //  Get the cluster database information
        foreach ($_cluster->getDatabaseConfig() as $_key => $_value) {
            $_vars['DB_' . strtr(strtoupper($_key), '-', '_')] = $_value;
        }

        //  Throw in some paths
        if (!empty($_paths = $_cluster->getConfig('paths', []))) {
            foreach ($_paths as $_key => $_value) {
                $_vars['DF_MANAGED_' . strtr(strtoupper($_key), '-', '_')] = $_value;
            }
        }

        //  Now jam everything into the environment
        foreach ($_vars as $_key => $_value) {
            putenv($_key . '=' . $_value);
            $_ENV[$_key] = $_value;
            $_SERVER[$_key] = $_value;
        }
    }
}