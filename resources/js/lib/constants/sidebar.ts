import {
    Flag,
    Gift,
    Inbox,
    LayoutGrid,
    LogOut,
    MessageSquare,
    Settings,
    Shapes,
    SlidersHorizontal,
    type LucideIcon,
} from 'lucide-react';

export type SidebarItem = {
    label: string;
    icon: LucideIcon;
    path: string;
};

export const CMS_SIGN_OUT_PATH = '/cms/logout';

export const sidebar: SidebarItem[] = [
    { label: 'Dashboard', icon: LayoutGrid, path: '/cms/dashboard' },
    { label: 'Vendor approvals', icon: Inbox, path: '/cms/vendor-approvals' },
    { label: 'Category manager', icon: Shapes, path: '/cms/category-manager' },
    { label: 'Report queue', icon: Flag, path: '/cms/report-queue' },
    { label: 'Loyalty points', icon: Gift, path: '/cms/loyalty-points' },
    { label: 'Support desk', icon: MessageSquare, path: '/cms/support-desk' },
    { label: 'Nudge rules', icon: SlidersHorizontal, path: '/cms/nudge-rules' },
    { label: 'Settings', icon: Settings, path: '/cms/settings' },
    { label: 'Logout', icon: LogOut, path: CMS_SIGN_OUT_PATH },
];
