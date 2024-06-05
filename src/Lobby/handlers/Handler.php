<?php

namespace Lobby\handlers;

abstract class Handler
{
    abstract public function onLoad(): void;
}