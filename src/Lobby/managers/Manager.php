<?php

namespace Lobby\managers;

abstract class Manager
{
    protected bool $requireSaveOnDisable = false;

    /**
     * @return bool
     */
    public function isRequireSaveOnDisable(): bool
    {
        return $this->requireSaveOnDisable;
    }

    /**
     * @param bool $requireSaveOnDisable
     */
    public function setRequireSaveOnDisable(bool $requireSaveOnDisable): void
    {
        $this->requireSaveOnDisable = $requireSaveOnDisable;
    }

    /**
     * @return void
     */
    abstract public function onLoad(): void;

    public function save(): void {}
}
