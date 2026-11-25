import { useForm } from '@inertiajs/react';
import PrimaryButton from '@/Components/buttons/primary-button';
import { Stepper, Toggle } from '@/Components/ui/controls';
import CmsLayout from '@/Layouts/cms-layout';
import type { ReactNode } from 'react';

type Rules = {
    daily_nudge_cap: number;
    same_vendor_gap_days: number;
    walking_min_m: number;
    walking_max_m: number;
    trigger_min_m: number;
    no_wait_minutes: number;
    auto_pause_reports: number;
    combined_nudge: boolean;
    weekly_summary_fallback: boolean;
    vendor_confirmation_required: boolean;
    free_windows_only: boolean;
};

type NumberKey = { [K in keyof Rules]: Rules[K] extends number ? K : never }[keyof Rules];
type BoolKey = { [K in keyof Rules]: Rules[K] extends boolean ? K : never }[keyof Rules];

function RuleCard({ title, description, error, children }: { title: string; description: string; error?: string; children: ReactNode }) {
    return (
        <div className="card rule-card">
            <h2 className="card-title">{title}</h2>
            <p className="muted small rule-desc">{description}</p>
            {children}
            {error && <p className="field-error mt-2">{error}</p>}
        </div>
    );
}

export default function NudgeRules({ rules }: { rules: Rules }) {
    const form = useForm<Rules>(rules);
    const dirty = (Object.keys(rules) as (keyof Rules)[]).some((k) => form.data[k] !== rules[k]);

    const stepper = (key: NumberKey, min: number, max: number, step = 1, suffix?: string) => (
        <Stepper value={form.data[key]} onChange={(v) => form.setData(key, v)} min={min} max={max} step={step} suffix={suffix} />
    );

    const toggle = (key: BoolKey, on: string, off: string) => <Toggle checked={form.data[key]} onChange={(v) => form.setData(key, v)} label={form.data[key] ? on : off} />;

    return (
        <CmsLayout title="Nudge rules">
            <p className="muted">Rules change without an app update. Any check that fails keeps Najik silent.</p>

            <form
                className="stack"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/cms/nudge-rules', { preserveScroll: true });
                }}
            >
                <div className="rules-grid">
                    <RuleCard title="Daily nudge cap" description="Maximum nudges per user per day (users can lower it)." error={form.errors.daily_nudge_cap}>
                        {stepper('daily_nudge_cap', 1, 5)}
                    </RuleCard>
                    <RuleCard title="Same-vendor gap" description="The same vendor can nudge a user at most once in this period." error={form.errors.same_vendor_gap_days}>
                        {stepper('same_vendor_gap_days', 1, 60, 1, 'days')}
                    </RuleCard>
                    <RuleCard title="Walking distance" description="Nudge only when the user is within this distance of a match." error={form.errors.walking_min_m || form.errors.walking_max_m}>
                        <div className="rule-row">
                            {stepper('walking_min_m', 100, 2000, 50, 'm')}
                            <span className="muted">to</span>
                            {stepper('walking_max_m', 100, 3000, 50, 'm')}
                        </div>
                    </RuleCard>
                    <RuleCard title="Trigger distance (minimum)" description="Alerts fire no closer than this, because phone alerts are unreliable below it." error={form.errors.trigger_min_m}>
                        {stepper('trigger_min_m', 50, 1000, 25, 'm')}
                    </RuleCard>
                    <RuleCard title="“No wait now” expiry" description="The vendor status switches itself off after this time unless renewed." error={form.errors.no_wait_minutes}>
                        {stepper('no_wait_minutes', 10, 240, 5, 'min')}
                    </RuleCard>
                    <RuleCard title="Auto-pause after reports" description="A vendor is paused automatically after this many open reports." error={form.errors.auto_pause_reports}>
                        {stepper('auto_pause_reports', 1, 20)}
                    </RuleCard>
                    <RuleCard title="Combined nudge" description="When two wishlist items match nearby, send one combined nudge." error={form.errors.combined_nudge}>
                        {toggle('combined_nudge', 'Enabled', 'Disabled')}
                    </RuleCard>
                    <RuleCard title="Weekly summary fallback" description="If nudges go unopened for two weeks, switch to a weekly summary." error={form.errors.weekly_summary_fallback}>
                        {toggle('weekly_summary_fallback', 'Enabled', 'Disabled')}
                    </RuleCard>
                    <RuleCard title="Vendor daily confirmation" description="Only vendors who confirmed “open today” can trigger nudges." error={form.errors.vendor_confirmation_required}>
                        {toggle('vendor_confirmation_required', 'Required', 'Not required')}
                    </RuleCard>
                    <RuleCard title="Free-time windows only" description="Location watching and nudges only inside the user’s chosen windows." error={form.errors.free_windows_only}>
                        {toggle('free_windows_only', 'Enforced', 'Not enforced')}
                    </RuleCard>
                </div>

                <div className="flex gap-3">
                    <PrimaryButton variant="outline" size="medium" disabled={!dirty || form.processing} onClick={() => form.reset()}>
                        Discard
                    </PrimaryButton>
                    <PrimaryButton type="submit" size="medium" disabled={!dirty || form.processing}>
                        Save changes
                    </PrimaryButton>
                </div>
            </form>
        </CmsLayout>
    );
}
