<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReportReason;
use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Enums\ShopStatus;
use App\Models\Report;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ShopStatusService $shopStatus,
    ) {}

    /**
     * Files a report; enough open reports pause the vendor automatically.
     *
     * @throws ValidationException
     */
    public function create(User $user, Shop $shop, ReportReason $reason, ?string $note): Report
    {
        $duplicate = Report::query()
            ->where('user_id', $user->id)
            ->where('shop_id', $shop->id)
            ->where('status', ReportStatus::OPEN)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages(['reason' => 'You already reported this shop. We are looking into it.']);
        }

        return DB::transaction(function () use ($user, $shop, $reason, $note) {
            $report = Report::query()->create([
                'user_id' => $user->id,
                'shop_id' => $shop->id,
                'reason' => $reason,
                'note' => $note,
                'status' => ReportStatus::OPEN,
            ]);

            $openReports = Report::query()->where('shop_id', $shop->id)->where('status', ReportStatus::OPEN)->count();

            if ($shop->isLive() && $openReports >= $this->settings->int('auto_pause_reports')) {
                $this->shopStatus->change($shop, ShopStatus::PAUSED, null, "Auto-paused after {$openReports} open reports.");
                $shop->forceFill(['auto_paused_at' => now(), 'open_today_on' => null, 'no_wait_until' => null])->save();
            }

            return $report;
        });
    }

    /**
     * Resolves every open report for the shop together, as the CMS queue does.
     */
    public function resolve(Report $report, User $admin, ReportResolution $resolution, ?string $note): void
    {
        DB::transaction(function () use ($report, $admin, $resolution, $note) {
            $shop = $report->shop;

            Report::query()
                ->where('shop_id', $shop->id)
                ->where('status', ReportStatus::OPEN)
                ->update([
                    'status' => ReportStatus::RESOLVED->value,
                    'resolution' => $resolution->value,
                    'resolution_note' => $note,
                    'resolved_by' => $admin->id,
                    'resolved_at' => now(),
                ]);

            if ($resolution === ReportResolution::VENDOR_PAUSED && $shop->status !== ShopStatus::PAUSED) {
                $this->shopStatus->change($shop, ShopStatus::PAUSED, $admin, $note ?? 'Paused after report review.');
            }

            if ($resolution === ReportResolution::VENDOR_RESTORED || ($resolution === ReportResolution::DISMISSED && $shop->auto_paused_at !== null)) {
                if ($shop->status === ShopStatus::PAUSED) {
                    $this->shopStatus->change($shop, ShopStatus::APPROVED, $admin, $note ?? 'Restored after report review.');
                }
                $shop->forceFill(['auto_paused_at' => null])->save();
            }
        });
    }
}
