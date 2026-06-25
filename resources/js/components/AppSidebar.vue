<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import {
    dashboard,
    posts,
    projects,
    users as usersRoute,
    categories,
    tags,
    licenses,
    servers,
    websites,
    requestlogsIndex,
} from '@/routes';
import {
    FileText,
    FolderGit2,
    LayoutGrid,
    BookOpen,
    FolderKanban,
    Users as UsersIcon,
    Folder,
    Tag,
    Key,
    Server,
    Globe,
    ScrollText,
    RefreshCw,
} from 'lucide-vue-next';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Posts',
        href: posts(),
        icon: FileText,
    },
    {
        title: 'Projects',
        href: projects(),
        icon: FolderKanban,
    },
    {
        title: 'Users',
        href: usersRoute(),
        icon: UsersIcon,
    },
    {
        title: 'Categories',
        href: categories(),
        icon: Folder,
    },
    {
        title: 'Tags',
        href: tags(),
        icon: Tag,
    },
    {
        title: 'Licenses',
        href: licenses(),
        icon: Key,
    },
    {
        title: 'Websites',
        href: websites(),
        icon: Globe,
    },
    {
        title: 'Servers',
        href: servers(),
        icon: Server,
    },
    {
        title: 'Request Logs',
        href: requestlogsIndex(),
        icon: ScrollText,
    },
    {
        title: 'System Update',
        href: '/admin/system/update',
        icon: RefreshCw,
    },
];

const footerNavItems: NavItem[] = [
    // {
    //     title: 'Repository',
    //     href: 'https://github.com/laravel/vue-starter-kit',
    //     icon: FolderGit2,
    // },
    // {
    //     title: 'Documentation',
    //     href: 'https://laravel.com/docs/starter-kits#vue',
    //     icon: BookOpen,
    // },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
