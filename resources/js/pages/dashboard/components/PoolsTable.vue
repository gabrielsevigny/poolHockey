<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import type { ColumnDef, SortingState } from '@tanstack/vue-table';
import {
    FlexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useVueTable,
} from '@tanstack/vue-table';
import { ArrowUpDown } from 'lucide-vue-next';
import { computed, h, ref } from 'vue';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Pool } from '@/types';

const props = defineProps<{
    pools: Pool[];
}>();

const navigateToPool = (poolId: number) => {
    router.visit(`/pools/${poolId}`);
};

// Configuration des statuts
const statusLabels: Record<Pool['status'], string> = {
    selection: 'Sélection',
    upcoming: 'Bientôt disponible',
    active: 'En cours',
    finished: 'Terminé',
};

const statusClasses: Record<Pool['status'], string> = {
    selection: 'bg-purple-500 text-white w-[150px]',
    upcoming: 'bg-indigo-500 text-white w-[150px]',
    active: 'bg-green-500 text-white w-[150px]',
    finished: 'bg-gray-200 text-black w-[150px]',
};

const rowBackgroundClasses: Record<Pool['status'], string> = {
    selection: 'bg-purple-50 dark:bg-purple-950/20',
    upcoming: 'bg-indigo-50 dark:bg-indigo-950/20',
    active: 'bg-green-50 dark:bg-green-950/20',
    finished: '',
};

// Ordre de priorité: selection → upcoming → active → finished
const statusOrder: Record<Pool['status'], number> = {
    selection: 0,
    upcoming: 1,
    active: 2,
    finished: 3,
};

// Filtrer et trier les données
const data = computed(() => {
    return props.pools.sort(
        (a, b) => statusOrder[a.status] - statusOrder[b.status],
    );
});

// Définition des colonnes
const columns: ColumnDef<Pool>[] = [
    {
        accessorKey: 'name',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => [
                    'Nom du pool',
                    h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
                ],
            );
        },
        cell: ({ row }) =>
            h('div', { class: 'text-left font-bold' }, row.getValue('name')),
    },
    {
        accessorKey: 'status',
        header: () => h('div', { class: 'text-left' }, 'Statut'),
        cell: ({ row }) => {
            const status = row.getValue('status') as Pool['status'];
            return h(
                Badge,
                { class: statusClasses[status] },
                () => statusLabels[status],
            );
        },
    },
    {
        accessorKey: 'participants_count',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => [
                    'Participants',
                    h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
                ],
            );
        },
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-left' },
                row.getValue('participants_count'),
            ),
    },
    {
        accessorKey: 'rule_setting',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => [
                    'Type de règlement',
                    h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
                ],
            );
        },
        cell: ({ row }) =>
            h('div', { class: 'text-left' }, row.getValue('rule_setting')),
    },
    {
        accessorKey: 'start_date',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => [
                    'Date de début',
                    h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
                ],
            );
        },
        cell: ({ row }) => {
            const date = row.getValue('start_date') as string;
            return h('div', { class: 'text-left' }, date);
        },
    },
    {
        accessorKey: 'end_date',
        header: ({ column }) => {
            return h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => [
                    'Date de fin',
                    h(ArrowUpDown, { class: 'ml-2 h-4 w-4' }),
                ],
            );
        },
        cell: ({ row }) => {
            const date = row.getValue('end_date') as string;
            return h('div', { class: 'text-left' }, date);
        },
    },
];

const sorting = ref<SortingState>([]);

const table = useVueTable({
    get data() {
        return data.value;
    },
    columns,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getSortedRowModel: getSortedRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    onSortingChange: (updaterOrValue) => {
        sorting.value =
            typeof updaterOrValue === 'function'
                ? updaterOrValue(sorting.value)
                : updaterOrValue;
    },
    state: {
        get sorting() {
            return sorting.value;
        },
    },
    initialState: {
        pagination: {
            pageSize: 10,
        },
    },
});
</script>

<template>
    <div class="w-full">
        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow
                        v-for="headerGroup in table.getHeaderGroups()"
                        :key="headerGroup.id"
                    >
                        <TableHead
                            v-for="header in headerGroup.headers"
                            :key="header.id"
                        >
                            <FlexRender
                                v-if="!header.isPlaceholder"
                                :render="header.column.columnDef.header"
                                :props="header.getContext()"
                            />
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <template v-if="table.getRowModel().rows?.length">
                        <TableRow
                            v-for="row in table.getRowModel().rows"
                            :key="row.id"
                            :data-state="row.getIsSelected() && 'selected'"
                            :class="rowBackgroundClasses[row.original.status]"
                            class="cursor-pointer hover:bg-accent/50"
                            @click="navigateToPool(row.original.id)"
                        >
                            <TableCell
                                v-for="cell in row.getVisibleCells()"
                                :key="cell.id"
                            >
                                <FlexRender
                                    :render="cell.column.columnDef.cell"
                                    :props="cell.getContext()"
                                />
                            </TableCell>
                        </TableRow>
                    </template>

                    <TableRow v-else>
                        <TableCell
                            :colspan="columns.length"
                            class="h-24 text-center"
                        >
                            Aucun résultat.
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
        <div class="flex items-center justify-end space-x-2 py-4">
            <div class="flex-1 text-sm text-muted-foreground">
                Page {{ table.getState().pagination.pageIndex + 1 }} sur
                {{ table.getPageCount() }}
            </div>
            <div class="space-x-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!table.getCanPreviousPage()"
                    @click="table.previousPage()"
                >
                    Précédent
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="!table.getCanNextPage()"
                    @click="table.nextPage()"
                >
                    Suivant
                </Button>
            </div>
        </div>
    </div>
</template>
