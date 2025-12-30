<?php

namespace App\Providers;

use App\Services\SiteScanner;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Native\Laravel\Facades\Menu;
use Native\Laravel\Facades\MenuBar;

class NativeAppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        MenuBar::create()
            ->icon(resource_path('icon.png'))
            ->withContextMenu(
                Menu::new()
                    ->label('Launchpad')
                    ->separator()
                    ->submenu('Sites', $this->sitesMenu())
                    ->submenu('Services', $this->servicesMenu())
                    ->separator()
                    ->link('Open Config', 'file://'.$this->getConfigPath())
                    ->separator()
                    ->button('Start All', 'start')
                    ->button('Stop All', 'stop')
                    ->separator()
                    ->button('Quit', 'quit')
            );
    }

    protected function sitesMenu(): \Native\Laravel\Menu\Menu
    {
        $menu = Menu::new();

        try {
            $siteScanner = app(SiteScanner::class);
            $sites = $siteScanner->scan();

            if (empty($sites)) {
                $menu->label('No sites found');
            } else {
                foreach ($sites as $site) {
                    $phpInfo = "PHP {$site['php_version']}";
                    $menu->link(
                        "{$site['domain']} ({$phpInfo})",
                        "https://{$site['domain']}"
                    );
                }
            }
        } catch (\Exception $e) {
            $menu->label('Error loading sites');
        }

        return $menu;
    }

    protected function servicesMenu(): \Native\Laravel\Menu\Menu
    {
        return Menu::new()
            ->label('PHP Containers')
            ->link('Mailpit', 'http://localhost:8025')
            ->separator()
            ->label('Database')
            ->label('  Postgres: localhost:5432')
            ->label('  Redis: localhost:6379');
    }

    protected function getConfigPath(): string
    {
        return $_SERVER['HOME'].'/.config/launchpad';
    }

    public function register(): void
    {
        //
    }
}
