<?php

if (file_exists(dirname(__DIR__).'/var/cache/production/App_KernelProductionDebugContainer.preload.php')) {
    require dirname(__DIR__).'/var/cache/production/App_KernelProductionDebugContainer.preload.php';
}
if (file_exists(dirname(__DIR__).'/var/cache/sandbox/App_KernelSandboxDebugContainer.preload.php')) {
    require dirname(__DIR__).'/var/cache/sandbox/App_KernelSandboxDebugContainer.preload.php';
}
if (file_exists(dirname(__DIR__).'/var/cache/staging/App_KernelStagingDebugContainer.preload.php')) {
    require dirname(__DIR__).'/var/cache/staging/App_KernelStagingDebugContainer.preload.php';
}
