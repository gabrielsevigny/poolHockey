<script setup lang="ts">
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Player } from '@/types';

defineProps<{
    players: Player[];
    totalGoals: number;
    totalAssists: number;
    totalShutouts: number;
    totalVictories: number;
    totalDefeats: number;
    totalPoints: number;
}>();
</script>

<template>
    <div class="w-full">
        <div class="rounded-md border">
            <Table>
                <TableHeader class="bg-gray-900">
                    <TableRow class="hover:bg-gray-900">
                        <TableHead class="min-w-[250px] font-bold text-white">
                            Équipe
                        </TableHead>
                        <TableHead class="min-w-[250px] font-bold text-white">
                            Joueurs
                        </TableHead>
                        <TableHead
                            class="w-30 text-center font-bold text-white"
                        >
                            Pos.
                        </TableHead>
                        <TableHead
                            class="w-30 text-center font-bold text-white"
                        >
                            Buts
                        </TableHead>
                        <TableHead
                            class="w-30 text-center font-bold text-white"
                        >
                            Assists
                        </TableHead>
                        <TableHead
                            class="w-30 text-center font-bold text-white"
                        >
                            Blanchissage
                        </TableHead>
                        <TableHead
                            class="w-30 text-center font-bold text-white"
                        >
                            Victoire
                        </TableHead>
                        <TableHead
                            class="w-30 text-center font-bold text-white"
                        >
                            Défaite
                        </TableHead>
                        <TableHead
                            class="w-30 text-center font-bold text-white"
                        >
                            PTS
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(player, index) in players"
                        :key="index"
                        class="hover:bg-transparent"
                    >
                        <TableCell>{{ player.team }}</TableCell>
                        <TableCell class="font-medium">
                            {{ player.name }}
                        </TableCell>
                        <TableCell class="text-center">{{
                            player.position
                        }}</TableCell>
                        <TableCell class="text-center">{{
                            player.goals
                        }}</TableCell>
                        <TableCell class="text-center">{{
                            player.assists
                        }}</TableCell>
                        <TableCell class="text-center">{{
                            player.shutouts
                        }}</TableCell>
                        <TableCell class="text-center">{{
                            player.victories
                        }}</TableCell>
                        <TableCell class="text-center">{{
                            player.defeats
                        }}</TableCell>
                        <TableCell class="text-center font-bold">
                            {{ player.points }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
