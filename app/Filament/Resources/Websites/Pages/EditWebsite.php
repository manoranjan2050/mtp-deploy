<?php

declare(strict_types=1);

namespace App\Filament\Resources\Websites\Pages;

use App\Actions\Websites\DeleteWebsiteAction;
use App\Filament\Resources\Websites\WebsiteResource;
use App\Models\Website;
use App\Services\Websites\WebsiteProvisioningService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWebsite extends EditRecord
{
    protected static string $resource = WebsiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (Website $record): void {
                    app(DeleteWebsiteAction::class)->handle($record);
                }),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Website $record */
        $record = $this->record;

        $result = app(WebsiteProvisioningService::class)->republish($record->fresh());

        if (! $result->successful) {
            Notification::make()
                ->title('Website updated, but nginx republish failed')
                ->body($result->errorOutput ?: 'See the activity log for details.')
                ->warning()
                ->persistent()
                ->send();
        }
    }
}
