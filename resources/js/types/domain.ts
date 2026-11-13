export type Workspace = {
    type: 'user' | 'household';
    uuid: string;
    name: string;
};

export type Owner = Workspace;

export type Category = {
    uuid: string;
    name: string;
    slug: string;
    sort_order: number;
    is_custom: boolean;
};

export type Store = {
    uuid: string;
    name: string;
    location: string | null;
};

export type ShoppingList = {
    uuid: string;
    title: string;
    description: string | null;
    status: 'active' | 'archived';
    owner: Owner | null;
    default_store?: Store | null;
    active_trip: string | null;
    is_favorite: boolean;
    needed_count?: number;
    item_count?: number;
    updated_at: string | null;
    can: {
        update: boolean;
        edit_items: boolean;
        run_trip: boolean;
        share: boolean;
        delete: boolean;
        restore: boolean;
        leave: boolean;
    };
};

export type ListItem = {
    uuid: string;
    name: string;
    quantity: string | null;
    unit: string | null;
    notes: string | null;
    status: 'needed' | 'satisfied';
    needs_review: boolean;
    sort_order: number;
    revision: number;
    category?: Category | null;
    product?: string | null;
    added_by?: string | null;
};

export type Suggestion = {
    name: string;
    category: string | null;
    is_favorite: boolean;
    reason?: string;
};

export type Recommendation = {
    product: string;
    name: string;
    category: string | null;
    kind: string;
    reason: string;
    owner: Owner | null;
};

export type StapleRule = {
    uuid: string;
    name: string;
    product: string;
    cadence_days: number | null;
    prediction_enabled: boolean;
    reminder_enabled: boolean;
    snoozed_until: string | null;
};

export type AppNotification = {
    id: string;
    type: string | null;
    title: string | null;
    body: string | null;
    url: string | null;
    read_at: string | null;
    created_at: string | null;
};

export type ListShare = {
    user: string;
    name: string;
    email: string;
    role: 'editor' | 'viewer';
};

export type ListShareInvitation = {
    uuid: string;
    email: string;
    role: 'editor' | 'viewer';
    expires_at: string;
    list_title?: string;
    list?: string;
};

export type HouseholdInvitation = {
    uuid: string;
    email: string;
    role: string;
    expires_at: string;
    household_name?: string;
    household?: string;
};

export type TripItem = {
    uuid: string;
    name: string;
    category: string | null;
    category_sort: number;
    planned_quantity: string | null;
    planned_unit: string | null;
    actual_quantity: string | null;
    line_total_minor?: number | null;
    notes: string | null;
    purchased: boolean;
    checked_by?: string | null;
    sort_order: number;
    revision: number;
};

export type Trip = {
    uuid: string;
    status: 'active' | 'completed' | 'cancelled';
    revision: number;
    list: {
        uuid: string;
        title: string;
        owner: Owner | null;
        deleted: boolean;
    };
    store?: Store | null;
    started_at: string;
    completed_at: string | null;
    cancelled_at: string | null;
    started_by?: string | null;
    completed_by?: string | null;
    item_count?: number;
    purchased_count?: number;
    items?: TripItem[];
    added_since?: {
        uuid: string;
        name: string;
        quantity: string | null;
        unit: string | null;
    }[];
    purchase?: string;
    total_minor?: number | null;
    currency_code?: string;
    can: { run: boolean; manage_amounts: boolean };
};

export type PurchaseItem = {
    uuid: string;
    description: string;
    quantity: string | null;
    unit: string | null;
    category: string | null;
    category_uuid: string | null;
    line_total_minor: number | null;
};

export type Purchase = {
    uuid: string;
    owner: Owner | null;
    merchant: string | null;
    purchased_at: string;
    purchased_on: string;
    currency_code: string;
    subtotal_minor: number | null;
    tax_minor: number | null;
    discount_minor: number | null;
    total_minor: number | null;
    entry_method: string;
    verification_status: string;
    revision: number;
    deleted_at: string | null;
    trip?: string | null;
    item_count?: number;
    items?: PurchaseItem[];
    can?: { delete: boolean; move: boolean };
};

export type SpendingRow = {
    total_minor: number;
    purchase_count: number;
};

export type SpendingSummary = {
    months: number;
    timezone: string;
    currencies: (SpendingRow & {
        currency_code: string;
        by_month: (SpendingRow & { month: string })[];
        by_store: (SpendingRow & { name: string | null })[];
        itemized_minor: number;
        by_category: SpendingCategory[];
        comparison: SpendingComparison | null;
    })[];
    missing_total_count: number;
    needs_review_count: number;
};

export type SpendingCategory = SpendingRow & {
    name: string | null;
    by_month: (SpendingRow & { month: string })[];
};

export type SpendingComparison = {
    month: string;
    total_minor: number;
    previous_month_minor: number | null;
    last_year_minor: number | null;
};

export type PricedProduct = {
    uuid: string;
    name: string;
    currency_code: string;
    unit: string;
    purchase_count: number;
    latest_minor: number;
    first_minor: number;
    latest_at: string;
};

export type PricePoint = {
    purchased_at: string;
    store: string | null;
    unit_price_minor: number;
};

export type PriceSeries = {
    currency_code: string;
    unit: string;
    prices: PricePoint[];
    stores: {
        name: string | null;
        purchase_count: number;
        average_minor: number;
        latest_minor: number;
        latest_at: string;
    }[];
};

export type ProductPrices = {
    uuid: string;
    name: string;
    series: PriceSeries[];
};

export type Household = {
    uuid: string;
    name: string;
    timezone: string;
    currency_code: string;
    role: 'owner' | 'admin' | 'member' | null;
    member_count?: number;
};

export type Member = {
    uuid: string;
    name: string;
    email: string;
    role: 'owner' | 'admin' | 'member';
    joined_at: string;
};
