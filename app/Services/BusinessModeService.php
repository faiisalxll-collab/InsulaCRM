<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Showing;

class BusinessModeService
{
    /**
     * Selectable business modes. The mode decides which modules, pipeline
     * stages, lead statuses and roles a tenant sees.
     */
    public const MODES = [
        'wholesale'  => 'Wholesaling',
        'realestate' => 'Real Estate Agent / Broker',
    ];

    // ── Wholesale pipeline stages (current default) ──

    public const WHOLESALE_STAGES = [
        'prospecting'     => 'Prospecting',
        'contacting'      => 'Contacting',
        'engaging'        => 'Engaging',
        'offer_presented' => 'Offer Presented',
        'under_contract'  => 'Under Contract',
        'dispositions'    => 'Dispositions',
        'assigned'        => 'Assigned',
        'closing'         => 'Closing',
        'closed_won'      => 'Closed Won',
        'closed_lost'     => 'Closed Lost',
    ];

    // ── Real estate agent pipeline stages ──

    public const REALESTATE_STAGES = [
        'lead'              => 'عميل',
        'listing_agreement' => 'اتفاقية تسويق',
        'active_listing'    => 'عقار نشط',
        'showing'           => 'معاينة',
        'offer_received'    => 'عرض',
        'negotiating'       => 'تفاوض',
        'under_contract'    => 'اتفاق',
        'inspection'        => 'مراجعة',
        'appraisal'         => 'تقييم',
        'closing'           => 'إغلاق',
        'closed_won'        => 'مكتملة',
        'closed_lost'       => 'لم تتم',
    ];

    // ── Wholesale lead statuses ──

    public const WHOLESALE_LEAD_STATUSES = [
        'new'             => 'New',
        'prospecting'     => 'Prospecting',
        'contacting'      => 'Contacting',
        'contacted'       => 'Contacted',
        'engaging'        => 'Engaging',
        'negotiating'     => 'Negotiating',
        'offer_presented' => 'Offer Presented',
        'under_contract'  => 'Under Contract',
        'assigned'        => 'Assigned',
        'dispositions'    => 'Dispositions',
        'closing'         => 'Closing',
        'closed_won'      => 'Closed Won',
        'closed_lost'     => 'Closed Lost',
        'dead'            => 'Dead',
    ];

    // ── Real estate lead statuses ──

    public const REALESTATE_LEAD_STATUSES = [
        'new'           => 'جديد',
        'inquiry'       => 'استفسار',
        'consultation'  => 'تواصل ومتابعة',
        'active_client' => 'عميل نشط',
        'nurture'       => 'متابعة لاحقة',
        'closed_won'    => 'تمت الصفقة',
        'closed_lost'   => 'لم تتم',
        'dead'          => 'غير نشط',
    ];

    // ── Wholesale distress markers ──

    public const WHOLESALE_DISTRESS_MARKERS = [
        'tax_delinquent'   => 'Tax Delinquent',
        'code_violation'   => 'Code Violation',
        'absentee_owner'   => 'Absentee Owner',
        'probate'          => 'Probate',
        'pre_foreclosure'  => 'Pre-Foreclosure',
        'divorce'          => 'Divorce',
        'out_of_state_owner' => 'Out of State Owner',
        'utility_shutoff'  => 'Utility Shutoff',
        'fire_damage'      => 'Fire Damage',
        'vacant'           => 'Vacant',
    ];

    // ── Wholesale lead sources ──

    public const WHOLESALE_LEAD_SOURCES = [
        'cold_call'            => 'Cold Call',
        'direct_mail'          => 'Direct Mail',
        'website'              => 'Website',
        'referral'             => 'Referral',
        'driving_for_dollars'  => 'Driving for Dollars',
        'ppc'                  => 'PPC / Paid Ads',
        'seo'                  => 'SEO / Organic',
        'social_media'         => 'Social Media',
        'list_import'          => 'List Import',
        'api'                  => 'API / Integration',
        'other'                => 'Other',
    ];

    // ── Real estate lead sources ──

    public const REALESTATE_LEAD_SOURCES = [
        'website'         => 'موقع المكتب',
        'referral'        => 'إحالة',
        'office_walk_in'  => 'زيارة المكتب',
        'phone_call'      => 'اتصال هاتفي',
        'whatsapp'        => 'واتساب',
        'social_media'    => 'وسائل التواصل',
        'property_portal' => 'منصة عقارية',
        'open_house'      => 'معاينة أو فعالية',
        'past_client'     => 'عميل سابق',
        'broker_referral' => 'إحالة وسيط',
        'api'             => 'تكامل',
        'other'           => 'أخرى',
    ];

    // Accepted for editing/importing older records, but not shown for new Saudi clients.
    public const REALESTATE_LEGACY_LEAD_SOURCES = [
        'sign_call', 'ppc', 'seo', 'zillow', 'realtor_com', 'mls', 'sphere',
    ];

    // ── Wholesale roles ──

    public const WHOLESALE_ROLES = [
        'admin', 'acquisition_agent', 'disposition_agent', 'field_scout', 'agent',
    ];

    // ── Real estate roles ──

    public const REALESTATE_ROLES = [
        'admin', 'listing_agent', 'buyers_agent', 'agent',
    ];

    /**
     * Override the mode for CLI/testing contexts where no auth tenant exists.
     * Call with null to clear the override.
     */
    protected static ?string $modeOverride = null;

    public static function setModeOverride(?string $mode): void
    {
        static::$modeOverride = $mode;
    }

    /**
     * Resolve the tenant from the given argument or the auth context.
     */
    protected static function resolveTenant(?Tenant $tenant = null): ?Tenant
    {
        if ($tenant) {
            return $tenant;
        }

        return auth()->check() ? auth()->user()->tenant : null;
    }

    /**
     * Get the business mode string for the tenant.
     */
    public static function mode(?Tenant $tenant = null): string
    {
        if (static::$modeOverride !== null && $tenant === null) {
            return static::$modeOverride;
        }

        $tenant = self::resolveTenant($tenant);
        return $tenant?->business_mode ?? 'wholesale';
    }

    /**
     * Check if the resolved tenant is in wholesale mode.
     */
    public static function isWholesale(?Tenant $tenant = null): bool
    {
        return self::mode($tenant) === 'wholesale';
    }

    /**
     * Check if the resolved tenant is in real estate mode.
     */
    public static function isRealEstate(?Tenant $tenant = null): bool
    {
        return self::mode($tenant) === 'realestate';
    }

    // ─── Pipeline Stages ─────────────────────────────────────

    public static function getStages(?Tenant $tenant = null): array
    {
        return self::isRealEstate($tenant)
            ? self::REALESTATE_STAGES
            : self::WHOLESALE_STAGES;
    }

    public static function getDefaultStage(?Tenant $tenant = null): string
    {
        return self::isRealEstate($tenant) ? 'lead' : 'prospecting';
    }

    public static function getStageLabel(string $stage, ?Tenant $tenant = null): string
    {
        $stages = self::getStages($tenant);
        return __($stages[$stage] ?? ucwords(str_replace('_', ' ', $stage)));
    }

    public static function getStageLabels(?Tenant $tenant = null): array
    {
        return array_map(fn($label) => __($label), self::getStages($tenant));
    }

    // ─── Buyer Match Trigger Stage ───────────────────────────

    public static function getBuyerMatchTriggerStage(?Tenant $tenant = null): string
    {
        return self::isRealEstate($tenant) ? 'active_listing' : 'dispositions';
    }

    // ─── Terminology ─────────────────────────────────────────

    public static function getTerminology(?Tenant $tenant = null): array
    {
        if (self::isRealEstate($tenant)) {
            return [
                'money_label'    => __('Commission'),
                'money_field'    => 'total_commission',
                'buyer_label'    => __('Clients'),
                'buyer_singular' => __('Client'),
                'seller_label'   => __('Sellers'),
                'pipeline_label' => __('Transactions'),
                'deal_label'     => __('Transaction'),
                'fee_label'      => __('in commission'),
            ];
        }

        return [
            'money_label'    => __('Assignment Fee'),
            'money_field'    => 'assignment_fee',
            'buyer_label'    => __('Buyers'),
            'buyer_singular' => __('Buyer'),
            'seller_label'   => __('Sellers'),
            'pipeline_label' => __('Pipeline'),
            'deal_label'     => __('Deal'),
            'fee_label'      => __('in fees'),
        ];
    }

    // ─── Custom Field Defaults ───────────────────────────────

    public static function getCustomFieldDefaults(string $fieldType, ?Tenant $tenant = null): array
    {
        $isRealEstate = self::isRealEstate($tenant);

        return match ($fieldType) {
            'lead_status' => $isRealEstate
                ? self::REALESTATE_LEAD_STATUSES
                : self::WHOLESALE_LEAD_STATUSES,

            'distress_markers' => $isRealEstate
                ? [] // Real estate agents don't use distress markers
                : self::WHOLESALE_DISTRESS_MARKERS,

            'lead_source' => $isRealEstate
                ? self::REALESTATE_LEAD_SOURCES
                : self::WHOLESALE_LEAD_SOURCES,

            'property_type' => $isRealEstate
                ? [
                    'villa'       => 'فيلا',
                    'apartment'   => 'شقة',
                    'floor'       => 'دور',
                    'land'        => 'أرض',
                    'building'    => 'عمارة',
                    'rest_house'  => 'استراحة',
                    'farm'        => 'مزرعة',
                    'commercial'  => 'تجاري',
                    'other'       => 'أخرى',
                ]
                : [
                    'single_family' => 'Single Family',
                    'multi_family'  => 'Multi Family',
                    'condo'         => 'Condo',
                    'townhouse'     => 'Townhouse',
                    'commercial'    => 'Commercial',
                    'land'          => 'Land',
                    'other'         => 'Other',
                ],

            'property_condition' => [
                'excellent'  => 'Excellent',
                'good'       => 'Good',
                'fair'       => 'Fair',
                'poor'       => 'Poor',
                'distressed' => 'Distressed',
            ],

            'activity_type' => [
                'call'        => 'Call',
                'sms'         => 'SMS',
                'email'       => 'Email',
                'voicemail'   => 'Voicemail',
                'direct_mail' => 'Direct Mail',
                'note'        => 'Note',
                'meeting'     => 'Meeting',
            ],

            'contact_type' => self::isRealEstate($tenant)
                ? [
                    'seller_lead'   => __('Seller Lead'),
                    'buyer_lead'    => __('Buyer Lead'),
                    'active_client' => __('Active Client'),
                    'past_client'   => __('Past Client'),
                ]
                : [],

            default => [],
        };
    }

    // ─── Field Visibility ────────────────────────────────────

    public static function getDealFields(?Tenant $tenant = null): array
    {
        if (self::isRealEstate($tenant)) {
            return [
                'contract_price', 'listing_commission_pct', 'buyer_commission_pct',
                'total_commission', 'mls_number', 'listing_date', 'days_on_market',
                'contract_date', 'closing_date', 'notes',
            ];
        }

        return [
            'contract_price', 'assignment_fee', 'earnest_money',
            'inspection_period_days', 'contract_date', 'due_diligence_end_date',
            'closing_date', 'notes',
        ];
    }

    public static function getPropertyFields(?Tenant $tenant = null): array
    {
        if (self::isRealEstate($tenant)) {
            return [
                'list_price', 'listing_status', 'listed_at', 'sold_at', 'sold_price',
                'asking_price', 'bedrooms', 'bathrooms', 'square_footage', 'lot_size',
                'year_built', 'property_type', 'transaction_type', 'district', 'plan_number',
                'area_sqm', 'facing', 'street_width_m', 'property_age_years',
                'floors', 'units', 'furnished', 'finance_eligible',
                'price_per_sqm', 'latitude', 'longitude',
            ];
        }

        return [
            'arv', 'asking_price', 'estimated_repair_cost', 'bedrooms', 'bathrooms',
            'square_footage', 'lot_size', 'year_built', 'property_type', 'condition',
            'distress_markers',
        ];
    }

    // ─── Roles ───────────────────────────────────────────────

    public static function getRoles(?Tenant $tenant = null): array
    {
        return self::isRealEstate($tenant)
            ? self::REALESTATE_ROLES
            : self::WHOLESALE_ROLES;
    }

    /**
     * Agent role names (excludes admin) for the current business mode.
     * Useful for building agent dropdowns, report filters, and team queries.
     */
    public static function getAgentRoleNames(?Tenant $tenant = null): array
    {
        return array_values(array_filter(self::getRoles($tenant), fn($r) => $r !== 'admin'));
    }

    /**
     * Role names that may own leads, deals, tasks and activities.
     *
     * Unlike getAgentRoleNames() this deliberately includes 'admin'. On a fresh
     * install the admin is the only user in the tenant, so excluding them left
     * the "Assigned Agent" dropdown with zero options and made it impossible to
     * create the first lead.
     */
    public static function getAssignableRoleNames(?Tenant $tenant = null): array
    {
        return self::getRoles($tenant);
    }

    /**
     * Pipeline stage keys valid for an explicit mode, independent of the
     * currently authenticated tenant. Used to preview the impact of a switch.
     */
    public static function getStagesForMode(string $mode): array
    {
        return $mode === 'realestate' ? self::REALESTATE_STAGES : self::WHOLESALE_STAGES;
    }

    /**
     * Lead status keys valid for an explicit mode, independent of the
     * currently authenticated tenant.
     */
    public static function getLeadStatusesForMode(string $mode): array
    {
        return $mode === 'realestate' ? self::REALESTATE_LEAD_STATUSES : self::WHOLESALE_LEAD_STATUSES;
    }

    /**
     * The mode a tenant would move to if it switched. Only two modes exist.
     */
    public static function oppositeMode(string $mode): string
    {
        return $mode === 'realestate' ? 'wholesale' : 'realestate';
    }

    public static function getRoleLabels(?Tenant $tenant = null): array
    {
        if (self::isRealEstate($tenant)) {
            return [
                'admin'        => __('Admin'),
                'listing_agent' => __('Listing Agent'),
                'buyers_agent' => __('Buyers Agent'),
                'agent'        => __('Agent'),
            ];
        }

        return [
            'admin'             => __('Admin'),
            'acquisition_agent' => __('Acquisition Agent'),
            'disposition_agent' => __('Disposition Agent'),
            'field_scout'       => __('Field Scout'),
            'agent'             => __('Agent'),
        ];
    }

    // ─── Dashboard KPI Config ────────────────────────────────

    public static function getDashboardKpiConfig(?Tenant $tenant = null): array
    {
        if (self::isRealEstate($tenant)) {
            return [
                'fee_column'   => 'total_commission',
                'fee_label'    => __('in commission'),
                'deal_label'   => __('Transactions'),
                'closed_label' => __('Closed'),
            ];
        }

        return [
            'fee_column'   => 'assignment_fee',
            'fee_label'    => __('in fees'),
            'deal_label'   => __('Deals'),
            'closed_label' => __('Closed Won'),
        ];
    }

    // ─── AI Context ──────────────────────────────────────────

    public static function getAiSystemContext(?Tenant $tenant = null): string
    {
        if (self::isRealEstate($tenant)) {
            return 'You are an AI assistant for a real estate agent CRM. The user is a real estate agent or broker who represents sellers listing properties and buyers purchasing homes. Focus on listings, showings, offers, commissions, and the traditional real estate sales cycle.';
        }

        return 'You are an AI assistant for a real estate wholesaling CRM. The user is a real estate wholesaler who acquires properties under contract from motivated sellers and assigns or sells those contracts to cash buyers. Focus on deal analysis, ARV, assignment fees, motivated seller signals, and disposition strategies.';
    }

    // ─── Motivation Score Weights ────────────────────────────

    public static function getScoreWeights(?Tenant $tenant = null): array
    {
        if (self::isRealEstate($tenant)) {
            return [
                'activity_engagement' => 40,
                'temperature'         => 25,
                'showing_history'     => 20,
                'source_quality'      => 15,
            ];
        }

        return [
            'list_stacking'    => 50,
            'temperature'      => 15,
            'activity'         => 15,
            'property_distress' => 20,
        ];
    }

    // ─── Custom Field Types Visibility ───────────────────────

    public static function getFieldTypes(?Tenant $tenant = null): array
    {
        $types = [
            'lead_status'       => __('Lead Statuses'),
            'property_type'     => __('Property Types'),
            'property_condition' => __('Property Conditions'),
            'activity_type'     => __('Activity Types'),
            'lead_source'       => __('Lead Sources'),
        ];

        // Only wholesale mode shows distress markers
        if (self::isWholesale($tenant)) {
            $types['distress_markers'] = __('Distress Markers');
        }

        return $types;
    }

    // ─── Showing & Open House Helpers ───────────────────────

    public static function getShowingOutcomes(): array
    {
        return Showing::OUTCOMES;
    }

    // ─── Offer Management Helpers ───────────────────────────

    public static function getFinancingTypes(): array
    {
        return [
            'cash' => 'نقدي',
            'bank_finance' => 'تمويل بنكي',
            'other' => 'أخرى',
        ];
    }

    // ─── Transaction Checklist Defaults ─────────────────────

    public static function getDefaultChecklistItems(): array
    {
        return \App\Models\TransactionChecklist::DEFAULT_ITEMS;
    }
}
