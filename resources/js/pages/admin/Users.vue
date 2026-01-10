<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Ban, Shield, Trash2 } from 'lucide-vue-next';

interface Role {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    is_banned: boolean;
    is_super_admin: boolean;
    banned_at: string | null;
    pools_count: number;
    roles: Role[];
}

interface Props {
    users: User[];
    roles: Role[];
    is_super_admin: boolean;
}

defineProps<Props>();

const handleRoleChange = (user: User, role: string) => {
    const form = useForm({
        role: role,
    });

    form.patch(route('admin.users.update-role', user.id), {
        preserveScroll: true,
    });
};

const toggleBan = (user: User) => {
    const action = user.is_banned ? 'débannir' : 'bannir';
    if (confirm(`Êtes-vous sûr de vouloir ${action} ${user.name} ?`)) {
        router.patch(
            route('admin.users.toggle-ban', user.id),
            {},
            { preserveScroll: true },
        );
    }
};

const handleDelete = (user: User) => {
    if (
        confirm(
            `Êtes-vous sûr de vouloir supprimer ${user.name} ? Cette action est irréversible.`,
        )
    ) {
        router.delete(route('admin.users.destroy', user.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Utilisateurs - Administration" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">
                        Gestion des utilisateurs
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Gérez les rôles, bannissements et suppressions
                    </p>
                </div>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nom</TableHead>
                            <TableHead>Email</TableHead>
                            <TableHead class="text-center">Rôle</TableHead>
                            <TableHead class="text-center">Pools</TableHead>
                            <TableHead class="text-center">Statut</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="user in users" :key="user.id">
                            <TableCell class="font-medium">{{
                                user.name
                            }}</TableCell>
                            <TableCell>{{ user.email }}</TableCell>
                            <TableCell class="text-center">
                                <Select
                                    :model-value="user.roles[0]?.name || 'user'"
                                    @update:model-value="
                                        (value) => handleRoleChange(user, value)
                                    "
                                    :disabled="user.is_super_admin"
                                >
                                    <SelectTrigger class="w-[140px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="role in roles"
                                            :key="role.id"
                                            :value="role.name"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <Shield
                                                    v-if="role.name === 'admin'"
                                                    class="h-3 w-3"
                                                />
                                                {{ role.name }}
                                            </div>
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge variant="secondary">{{
                                    user.pools_count
                                }}</Badge>
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge
                                    :variant="
                                        user.is_banned
                                            ? 'destructive'
                                            : 'default'
                                    "
                                >
                                    {{ user.is_banned ? 'Banni' : 'Actif' }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="toggleBan(user)"
                                        :title="
                                            user.is_banned
                                                ? 'Débannir'
                                                : 'Bannir'
                                        "
                                        :disabled="user.is_super_admin"
                                    >
                                        <Ban class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="handleDelete(user)"
                                        :disabled="user.is_super_admin"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>
    </AppLayout>
</template>
