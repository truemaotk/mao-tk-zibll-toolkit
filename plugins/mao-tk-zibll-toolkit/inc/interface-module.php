<?php
namespace MaoTK\ZibllToolkit;

defined('ABSPATH') || exit;

interface Module
{
    public function id(): string;
    public function name(): string;
    public function description(): string;
    public function requires_zibll(): bool;
    public function boot(): void;
}
