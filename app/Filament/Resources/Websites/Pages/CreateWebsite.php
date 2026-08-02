<?php

declare(strict_types=1);

namespace App\Filament\Resources\Websites\Pages;

use App\Actions\Websites\CreateWebsiteAction;
use App\DTOs\Websites\CreateWebsiteData;
use App\Enums\WebsiteFramework;
use App\Filament\Resources\Websites\WebsiteResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateWebsite extends CreateRecord
{
    protected static string $resource = WebsiteResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $result = app(CreateWebsiteAction::class)->handle(new CreateWebsiteData(
            serverId: $data['server_id'],
            name: $data['name'],
            domain: $data['domain'],
            phpVersion: $data['php_version'],
            framework: $data['framework'] instanceof WebsiteFramework
                ? $data['framework']
                : WebsiteFramework::from($data['framework']),
            aliases: $data['aliases'] ?? [],
            createdBy: auth()->id(),
        ));

        if (! $result['provisioning']->successful) {
            Notification::make()
                ->title('Website saved, but nginx provisioning failed')
                ->body($result['provisioning']->errorOutput ?: 'See the activity log for details.')
                ->warning()
                ->persistent()
                ->send();
        }

        return $result['website'];
    }
}
