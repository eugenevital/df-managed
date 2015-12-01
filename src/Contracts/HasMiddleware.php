<?php namespace DreamFactory\Managed\Contracts;

use Illuminate\Foundation\Http\Kernel;

/**
 * Allows services to be identified as having middleware
 *
 * @package DreamFactory\Managed\Contracts
 */
interface HasMiddleware
{
    //******************************************************************************
    //* Methods
    //******************************************************************************

    /**
     * Allows services to add middleware before the request is processed
     *
     * @param \Illuminate\Foundation\Http\Kernel $kernel
     *
     * @return $this
     */
    public function pushMiddleware(Kernel $kernel);
}
