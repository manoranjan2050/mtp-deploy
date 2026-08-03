<?php

declare(strict_types=1);

namespace App\Filament\Resources\Websites\Pages;

use App\Actions\Ssl\IssueLetsEncryptCertificateAction;
use App\Actions\Ssl\RenewCertificateAction;
use App\Actions\Ssl\RevokeCertificateAction;
use App\Actions\Ssl\UploadCustomCertificateAction;
use App\Filament\Resources\Websites\WebsiteResource;
use App\Models\SslCertificate;
use App\Models\Website;
use App\Services\Ssl\AcmeException;
use App\Services\Ssl\CertificateParserException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;

class ManageSsl extends Page
{
    use InteractsWithRecord;

    protected static string $resource = WebsiteResource::class;

    protected string $view = 'filament.resources.websites.pages.manage-ssl';

    #[Validate('required|string|max:500')]
    public string $domainsInput = '';

    public bool $useDnsChallenge = false;

    #[Validate('required|string|max:20000')]
    public string $uploadCertificatePem = '';

    #[Validate('required|string|max:20000')]
    public string $uploadPrivateKeyPem = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()->can('update', $this->getRecord()), 403);

        $this->domainsInput = $this->getRecord()->domain;
    }

    #[Computed]
    public function currentCertificate(): ?SslCertificate
    {
        /** @var Website $website */
        $website = $this->getRecord();

        return $website->currentCertificate();
    }

    /**
     * @return Collection<int, SslCertificate>
     */
    #[Computed]
    public function history(): Collection
    {
        /** @var Website $website */
        $website = $this->getRecord();

        return $website->certificates()->get();
    }

    public function issue(): void
    {
        $this->validate(['domainsInput' => 'required|string|max:500']);

        $domains = collect(explode(',', $this->domainsInput))
            ->map(fn (string $domain): string => trim($domain))
            ->filter()
            ->values()
            ->all();

        try {
            app(IssueLetsEncryptCertificateAction::class)->handle($this->getRecord(), $domains, $this->useDnsChallenge);

            Notification::make()->title('Certificate issued')->success()->send();
        } catch (AcmeException $exception) {
            Notification::make()->title('Issuance failed')->body($exception->getMessage())->danger()->send();
        }

        unset($this->currentCertificate, $this->history);
    }

    public function uploadCustom(): void
    {
        $this->validate();

        try {
            app(UploadCustomCertificateAction::class)->handle($this->getRecord(), $this->uploadCertificatePem, $this->uploadPrivateKeyPem);

            $this->uploadCertificatePem = '';
            $this->uploadPrivateKeyPem = '';

            Notification::make()->title('Certificate uploaded')->success()->send();
        } catch (CertificateParserException $exception) {
            Notification::make()->title('Upload failed')->body($exception->getMessage())->danger()->send();
        }

        unset($this->currentCertificate, $this->history);
    }

    public function renew(int $certificateId): void
    {
        $certificate = SslCertificate::query()->where('website_id', $this->getRecord()->id)->findOrFail($certificateId);

        try {
            app(RenewCertificateAction::class)->handle($certificate);

            Notification::make()->title('Certificate renewed')->success()->send();
        } catch (AcmeException $exception) {
            Notification::make()->title('Renewal failed')->body($exception->getMessage())->danger()->send();
        }

        unset($this->currentCertificate, $this->history);
    }

    public function revoke(int $certificateId): void
    {
        $certificate = SslCertificate::query()->where('website_id', $this->getRecord()->id)->findOrFail($certificateId);

        app(RevokeCertificateAction::class)->handle($certificate);

        unset($this->currentCertificate, $this->history);

        Notification::make()->title('Certificate revoked')->success()->send();
    }
}
