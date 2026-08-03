<?php

declare(strict_types=1);

namespace App\Filament\Resources\Websites\Pages;

use App\Actions\Cloudflare\ConnectCloudflareZoneAction;
use App\Actions\Cloudflare\CreateDnsRecordAction;
use App\Actions\Cloudflare\DeleteDnsRecordAction;
use App\Actions\Cloudflare\DisconnectCloudflareZoneAction;
use App\Actions\Cloudflare\PurgeCacheAction;
use App\Actions\Cloudflare\UpdateSslModeAction;
use App\DTOs\Cloudflare\DnsRecordData;
use App\Enums\CloudflareSslMode;
use App\Filament\Resources\Websites\WebsiteResource;
use App\Models\CloudflareZone;
use App\Models\Website;
use App\Services\Cloudflare\CloudflareZoneService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;

class ManageCloudflare extends Page
{
    use InteractsWithRecord;

    protected static string $resource = WebsiteResource::class;

    protected string $view = 'filament.resources.websites.pages.manage-cloudflare';

    #[Validate('required|string|max:255')]
    public string $zoneId = '';

    #[Validate('required|string|max:255')]
    public string $apiToken = '';

    #[Validate('required|string|in:A,AAAA,CNAME,TXT,MX')]
    public string $recordType = 'A';

    #[Validate('required|string|max:255')]
    public string $recordName = '';

    #[Validate('required|string|max:255')]
    public string $recordContent = '';

    public bool $recordProxied = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()->can('update', $this->getRecord()), 403);
    }

    #[Computed]
    public function zone(): ?CloudflareZone
    {
        /** @var Website $website */
        $website = $this->getRecord();

        return $website->cloudflareZone;
    }

    /**
     * @return list<DnsRecordData>
     */
    #[Computed]
    public function dnsRecords(): array
    {
        $zone = $this->zone();

        if ($zone === null) {
            return [];
        }

        $result = app(CloudflareZoneService::class)->listDnsRecords($zone);

        if (! $result->successful) {
            return [];
        }

        return $result->data;
    }

    public function connect(): void
    {
        $this->validate(['zoneId' => 'required|string|max:255', 'apiToken' => 'required|string|max:255']);

        app(ConnectCloudflareZoneAction::class)->handle($this->getRecord(), $this->zoneId, $this->apiToken);

        $this->zoneId = '';
        $this->apiToken = '';

        unset($this->zone, $this->dnsRecords);

        Notification::make()->title('Cloudflare zone connected')->success()->send();
    }

    public function disconnect(): void
    {
        $zone = $this->zone();

        if ($zone === null) {
            return;
        }

        app(DisconnectCloudflareZoneAction::class)->handle($zone);

        unset($this->zone, $this->dnsRecords);

        Notification::make()->title('Cloudflare zone disconnected')->success()->send();
    }

    public function updateSslMode(string $mode): void
    {
        $zone = $this->zone();

        if ($zone === null) {
            return;
        }

        $result = app(UpdateSslModeAction::class)->handle($zone, CloudflareSslMode::from($mode));

        unset($this->zone);

        Notification::make()
            ->title($result->successful ? 'SSL mode updated' : 'SSL mode update failed')
            ->body($result->successful ? null : implode(', ', $result->errors))
            ->success($result->successful)
            ->danger(! $result->successful)
            ->send();
    }

    public function purgeCache(): void
    {
        $zone = $this->zone();

        if ($zone === null) {
            return;
        }

        $result = app(PurgeCacheAction::class)->handle($zone);

        Notification::make()
            ->title($result->successful ? 'Cache purged' : 'Cache purge failed')
            ->body($result->successful ? null : implode(', ', $result->errors))
            ->success($result->successful)
            ->danger(! $result->successful)
            ->send();
    }

    public function createDnsRecord(): void
    {
        $this->validate();

        $zone = $this->zone();

        if ($zone === null) {
            return;
        }

        $result = app(CreateDnsRecordAction::class)->handle(
            $zone,
            $this->recordType,
            $this->recordName,
            $this->recordContent,
            proxied: $this->recordProxied,
        );

        if ($result->successful) {
            $this->recordName = '';
            $this->recordContent = '';
            $this->recordProxied = false;
        }

        unset($this->dnsRecords);

        Notification::make()
            ->title($result->successful ? 'DNS record created' : 'DNS record creation failed')
            ->body($result->successful ? null : implode(', ', $result->errors))
            ->success($result->successful)
            ->danger(! $result->successful)
            ->send();
    }

    public function deleteDnsRecord(string $recordId): void
    {
        $zone = $this->zone();

        if ($zone === null) {
            return;
        }

        $result = app(DeleteDnsRecordAction::class)->handle($zone, $recordId);

        unset($this->dnsRecords);

        Notification::make()
            ->title($result->successful ? 'DNS record deleted' : 'DNS record deletion failed')
            ->success($result->successful)
            ->danger(! $result->successful)
            ->send();
    }
}
