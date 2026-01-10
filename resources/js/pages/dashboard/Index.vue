<script setup lang="ts">
import CreatePoolModal from '@/components/CreatePoolModal.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem, type Pool } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import PoolsTable from './components/PoolsTable.vue';

interface TopScorer {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    position: string;
    sweater_number: number | null;
    team_name: string;
    team_abbrev: string;
    points: number;
    goals: number;
    assists: number;
    games_played: number;
    headshot_url: string;
}

interface RuleSetting {
    id: number;
    name: string;
    points_per_goal: number;
    points_per_assist: number;
    points_per_shutout: number;
    points_per_victory: number;
    points_per_defeat: number;
    points_per_overtime: number;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Props {
    pools: Pool[];
    topScorers: TopScorer[];
    ruleSettings: RuleSetting[];
    users: User[];
}

defineProps<Props>();

const createPoolModalOpen = ref(false);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Accueil',
        href: dashboard().url,
    },
];
</script>

<template>
    <Head title="Mon Pool" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="relative min-h-[100vh] flex-1 md:min-h-min">
                <div class="mb-6 flex items-center justify-between">
                    <h1 class="text-2xl font-semibold">Mes pools</h1>
                    <Button @click="createPoolModalOpen = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Ajouter un pool
                    </Button>
                </div>
                <PoolsTable :pools="pools" />
            </div>
        </div>

        <CreatePoolModal
            v-model:open="createPoolModalOpen"
            :rule-settings="ruleSettings"
            :users="users"
        />
    </AppLayout>
</template>
