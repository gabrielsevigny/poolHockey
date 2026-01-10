<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import PlayerDetailsModal from '@/components/PlayerDetailsModal.vue';
import PlayerSearchModal from '@/components/PlayerSearchModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Pool } from '@/types';
import { Filter, UserPlus, X } from 'lucide-vue-next';

interface PoolPlayer {
    id: number;
    nhl_player_id: number;
    player_name: string;
    position: string;
    team_abbrev: string;
    team_name: string;
    headshot_url: string;
    draft_order: number;
    games_in_pool: number;
    can_delete: boolean;
    stats: {
        goals: number;
        assists: number;
        points: number;
        games_played: number;
        plus_minus: number;
    };
    selected_by?: {
        id: number;
        name: string;
    };
}

interface PositionLimit {
    min: number;
    max: number;
}

interface ExtendedPool extends Pool {
    selected_players: PoolPlayer[];
    is_admin: boolean;
    current_user_player_count: number;
    max_players_per_user: number;
    position_limits?: Record<string, PositionLimit> | null;
    position_counts?: Record<string, number>;
}

const props = defineProps<{
    pool: ExtendedPool;
}>();

const statusLabels: Record<Pool['status'], string> = {
    selection: 'Sélection',
    upcoming: 'Bientôt disponible',
    active: 'En cours',
    finished: 'Terminé',
};

const statusClasses: Record<Pool['status'], string> = {
    selection: 'bg-purple-500 text-white',
    upcoming: 'bg-indigo-500 text-white',
    active: 'bg-green-500 text-white',
    finished: 'bg-gray-200 text-black',
};

const searchModalOpen = ref(false);
const detailsModalOpen = ref(false);
const selectedPlayer = ref<any>(null);

// Load filter states from localStorage
const STORAGE_KEY = 'pool_filters';

const loadFiltersFromStorage = () => {
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            return JSON.parse(stored);
        }
    } catch (e) {
        console.error('Error loading filters from storage:', e);
    }
    return null;
};

const savedFilters = loadFiltersFromStorage();

// Filter states with localStorage persistence
const showMyPlayersOnly = ref(savedFilters?.showMyPlayersOnly ?? false);
const sortBy = ref<string>(savedFilters?.sortBy ?? 'draft_order');
const filterByPosition = ref<string>(savedFilters?.filterByPosition ?? 'all');

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

// Get unique positions from selected players
const availablePositions = computed(() => {
    const positions = new Set(props.pool.selected_players?.map(p => p.position) || []);
    return Array.from(positions).sort();
});

// Filtered and sorted players
const filteredPlayers = computed(() => {
    let players = [...(props.pool.selected_players || [])];

    // Filter by current user
    if (showMyPlayersOnly.value && currentUserId.value) {
        players = players.filter(p => p.selected_by?.id === currentUserId.value);
    }

    // Filter by position
    if (filterByPosition.value !== 'all') {
        players = players.filter(p => p.position === filterByPosition.value);
    }

    // Sort players
    players.sort((a, b) => {
        switch (sortBy.value) {
            case 'draft_order':
                return a.draft_order - b.draft_order;
            case 'goals':
                return b.stats.goals - a.stats.goals;
            case 'assists':
                return b.stats.assists - a.stats.assists;
            case 'points':
                return b.stats.points - a.stats.points;
            case 'games_played':
                return b.stats.games_played - a.stats.games_played;
            case 'plus_minus':
                return b.stats.plus_minus - a.stats.plus_minus;
            default:
                return 0;
        }
    });

    return players;
});

// Count active filters
const activeFiltersCount = computed(() => {
    let count = 0;
    if (showMyPlayersOnly.value) count++;
    if (filterByPosition.value !== 'all') count++;
    if (sortBy.value !== 'draft_order') count++;
    return count;
});

const handlePlayerSelected = (player: any) => {
    selectedPlayer.value = player;
    searchModalOpen.value = false;
    detailsModalOpen.value = true;
};

const handlePlayerAdded = () => {
    // Reload the page to get updated player list
    router.reload({ only: ['pool'] });
};

const openSearchModal = () => {
    searchModalOpen.value = true;
};

const handleDeletePlayer = async (playerId: number) => {
    if (
        !confirm('Êtes-vous sûr de vouloir retirer ce joueur de votre équipe?')
    ) {
        return;
    }

    try {
        await axios.delete(`/pools/${props.pool.id}/players/${playerId}`);
        router.reload({ only: ['pool'] });
    } catch (error: any) {
        alert(error.response?.data?.message || 'Une erreur est survenue');
    }
};

const resetFilters = () => {
    showMyPlayersOnly.value = false;
    sortBy.value = 'draft_order';
    filterByPosition.value = 'all';
};

// Save filters to localStorage whenever they change
const saveFiltersToStorage = () => {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            showMyPlayersOnly: showMyPlayersOnly.value,
            sortBy: sortBy.value,
            filterByPosition: filterByPosition.value,
        }));
    } catch (e) {
        console.error('Error saving filters to storage:', e);
    }
};

// Check if current user has reached max players
const canAddMorePlayers = computed(
    () =>
        props.pool.current_user_player_count < props.pool.max_players_per_user,
);

// Watch filter changes and save to localStorage
watch([showMyPlayersOnly, sortBy, filterByPosition], () => {
    saveFiltersToStorage();
});

// Real-time updates via Laravel Echo
onMounted(() => {
    if (window.Echo) {
        window.Echo.channel(`pool.${props.pool.id}`)
            .listen('.player.selected', () => {
                // Reload pool data when a player is selected
                router.reload({ only: ['pool'] });
            })
            .listen('.player.removed', () => {
                // Reload pool data when a player is removed
                router.reload({ only: ['pool'] });
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave(`pool.${props.pool.id}`);
    }
});
</script>

<template>
    <Head title="Détails du Pool" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
            <div class="flex flex-wrap items-center justify-between gap-4 py-8">
                <div class="flex items-center gap-4">
                    <h1 class="mb-0 text-2xl font-bold">{{ pool.name }}</h1>
                    <Badge :class="statusClasses[pool.status]">
                        {{ statusLabels[pool.status] }}
                    </Badge>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="border-b-1 pb-4">
                        <CardTitle>Dates</CardTitle>
                        <CardDescription>Informations du pool</CardDescription>
                    </CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4 space-y-2">
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Repêchage
                            </p>
                            <p class="font-semibold">{{ pool.start_date }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">
                                Fin du pool
                            </p>
                            <p class="font-semibold">{{ pool.end_date }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="col-span-2">
                    <CardHeader class="border-b-1 pb-4">
                        <CardTitle>Règlements</CardTitle>
                        <CardDescription>{{
                            pool.rule_setting?.name
                        }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <div v-if="pool.rule_setting">
                            <ul class="grid grid-cols-4 gap-4 gap-y-2">
                                <li class="flex">
                                    <div class="mr-1 font-bold">Buts:</div>
                                    <div class="text-muted-foreground">
                                        {{
                                            pool.rule_setting.points_per_goal
                                        }}
                                        pts
                                    </div>
                                </li>
                                <li class="flex">
                                    <div class="mr-1 font-bold">Passes:</div>
                                    <div class="text-muted-foreground">
                                        {{
                                            pool.rule_setting.points_per_assist
                                        }}
                                        pts
                                    </div>
                                </li>
                                <li
                                    v-if="pool.rule_setting.points_per_shutout"
                                    class="flex"
                                >
                                    <div class="mr-1 font-bold">
                                        Blanchissages:
                                    </div>
                                    <div class="text-muted-foreground">
                                        {{
                                            pool.rule_setting.points_per_shutout
                                        }}
                                        pts
                                    </div>
                                </li>
                                <li
                                    v-if="pool.rule_setting.points_per_victory"
                                    class="flex"
                                >
                                    <div class="mr-1 font-bold">Victoires:</div>
                                    <div class="text-muted-foreground">
                                        {{
                                            pool.rule_setting.points_per_victory
                                        }}
                                        pts
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <!-- Position Limits -->
                        <div v-if="pool.position_limits" class="border-t pt-4">
                            <h4 class="mb-3 font-semibold">
                                Limites par position
                            </h4>
                            <div class="space-y-2 grid grid-cols-5 gap-2">
                                <div
                                    v-for="(
                                        limit, position
                                    ) in pool.position_limits"
                                    :key="position"
                                    class="flex items-center justify-between rounded-lg border bg-muted/30 px-3 py-2"
                                >
                                    <div class="flex items-center gap-2">
                                        <Badge variant="secondary" class="text-xs">{{ position }}</Badge>
                                    </div>
                                    <div class="text-xs font-medium">
                                        <span
                                            :class="[
                                                pool.position_counts?.[
                                                    position
                                                ] >= limit.max
                                                    ? 'text-green-600 dark:text-green-400'
                                                    : 'text-muted-foreground',
                                            ]"
                                        >
                                            {{
                                                pool.position_counts?.[
                                                    position
                                                ] || 0
                                            }}/{{ limit.max }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader
                    class="flex content-start items-start justify-between"
                >
                    <div>
                        <CardTitle> Joueurs sélectionnés </CardTitle>
                        <CardDescription>
                            {{ filteredPlayers.length }} / {{ pool.selected_players?.length || 0 }} joueur(s)
                            <span v-if="activeFiltersCount > 0" class="text-primary font-medium">
                                ({{ activeFiltersCount }} filtre{{ activeFiltersCount > 1 ? 's' : '' }} actif{{ activeFiltersCount > 1 ? 's' : '' }})
                            </span>
                        </CardDescription>
                    </div>

                    <div class="flex gap-2">
                        <!-- Filter Button -->
                        <Popover>
                            <PopoverTrigger as-child>
                                <Button variant="outline" class="gap-2 relative">
                                    <Filter class="h-4 w-4" />
                                    Filtres
                                    <Badge
                                        v-if="activeFiltersCount > 0"
                                        class="ml-1 h-5 w-5 rounded-full p-0 flex items-center justify-center text-xs"
                                        variant="default"
                                    >
                                        {{ activeFiltersCount }}
                                    </Badge>
                                </Button>
                            </PopoverTrigger>
                            <PopoverContent class="w-80" align="end">
                                <div class="space-y-4">
                                    <div class="space-y-2">
                                        <h4 class="font-medium leading-none">Filtres et tri</h4>
                                        <p class="text-sm text-muted-foreground">
                                            Personnalisez l'affichage des joueurs
                                        </p>
                                    </div>

                                    <Separator />

                                    <!-- Show My Players Only -->
                                    <div class="flex items-center justify-between space-x-2">
                                        <Label for="my-players" class="flex flex-col gap-1 cursor-pointer">
                                            <span class="font-medium">Mes joueurs seulement</span>
                                            <span class="text-xs text-muted-foreground font-normal">
                                                Afficher uniquement vos joueurs
                                            </span>
                                        </Label>
                                        <Switch
                                            id="my-players"
                                            v-model="showMyPlayersOnly"
                                        />
                                    </div>

                                    <Separator />

                                    <!-- Filter by Position -->
                                    <div class="space-y-2">
                                        <Label for="position-filter">Position</Label>
                                        <Select v-model="filterByPosition">
                                            <SelectTrigger id="position-filter">
                                                <SelectValue placeholder="Toutes les positions" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">
                                                    Toutes les positions
                                                </SelectItem>
                                                <SelectItem
                                                    v-for="position in availablePositions"
                                                    :key="position"
                                                    :value="position"
                                                >
                                                    {{ position }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <Separator />

                                    <!-- Sort By -->
                                    <div class="space-y-2">
                                        <Label for="sort-by">Trier par</Label>
                                        <Select v-model="sortBy">
                                            <SelectTrigger id="sort-by">
                                                <SelectValue placeholder="Ordre de sélection" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="draft_order">
                                                    Ordre de sélection
                                                </SelectItem>
                                                <SelectItem value="points">
                                                    Points (PTS)
                                                </SelectItem>
                                                <SelectItem value="goals">
                                                    Buts (B)
                                                </SelectItem>
                                                <SelectItem value="assists">
                                                    Passes (A)
                                                </SelectItem>
                                                <SelectItem value="games_played">
                                                    Matchs joués (MJ)
                                                </SelectItem>
                                                <SelectItem value="plus_minus">
                                                    Différentiel (+/-)
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <Separator />

                                    <!-- Reset Button -->
                                    <Button
                                        variant="outline"
                                        class="w-full"
                                        @click="resetFilters"
                                        :disabled="activeFiltersCount === 0"
                                    >
                                        Réinitialiser les filtres
                                    </Button>
                                </div>
                            </PopoverContent>
                        </Popover>

                        <!-- Add Player Button -->
                        <Button
                            v-if="pool.status === 'selection'"
                            @click="openSearchModal"
                            variant="outline"
                            class="gap-2"
                            :disabled="!canAddMorePlayers"
                        >
                            <UserPlus class="h-4 w-4" />
                            Rechercher un joueur
                            <span v-if="!canAddMorePlayers" class="ml-2 text-xs"
                                >({{ pool.current_user_player_count }}/{{
                                    pool.max_players_per_user
                                }})</span
                            >
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="
                            pool.selected_players &&
                            pool.selected_players.length > 0
                        "
                        class="rounded-md border"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-12">#</TableHead>
                                    <TableHead>Joueur</TableHead>
                                    <TableHead class="text-center"
                                        >POS</TableHead
                                    >
                                    <TableHead>Équipe</TableHead>
                                    <TableHead class="text-center"
                                        >MJ</TableHead
                                    >
                                    <TableHead class="text-center">B</TableHead>
                                    <TableHead class="text-center">A</TableHead>
                                    <TableHead class="text-center"
                                        >PTS</TableHead
                                    >
                                    <TableHead class="text-center"
                                        >+/-</TableHead
                                    >
                                    <TableHead>Sélectionné par</TableHead>
                                    <TableHead
                                        v-if="pool.status === 'selection'"
                                        class="w-12"
                                    ></TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="(
                                        player, index
                                    ) in filteredPlayers"
                                    :key="player.id"
                                    :class="{
                                        'bg-green-50 dark:bg-green-950/20':
                                            player.stats.plus_minus > 0,
                                        'bg-red-50 dark:bg-red-950/20':
                                            player.stats.plus_minus < 0,
                                    }"
                                >
                                    <TableCell class="font-bold">{{
                                        index + 1
                                    }}</TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-3">
                                            <img
                                                :src="player.headshot_url"
                                                :alt="player.player_name"
                                                class="h-12 w-12 rounded-full border-2 border-white bg-gradient-to-br from-gray-100 to-gray-200 object-cover shadow-md dark:border-gray-700 dark:from-gray-800 dark:to-gray-900"
                                                @error="
                                                    (e) =>
                                                        ((
                                                            e.target as HTMLImageElement
                                                        ).src = '/favicon.svg')
                                                "
                                            />
                                            <span class="font-semibold">{{
                                                player.player_name
                                            }}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell class="text-center">
                                        <Badge
                                            variant="secondary"
                                            class="text-xs"
                                            >{{ player.position }}</Badge
                                        >
                                    </TableCell>
                                    <TableCell>
                                        <div class="flex items-center gap-2">
                                            <img
                                                :src="`https://assets.nhle.com/logos/nhl/svg/${player.team_abbrev}_light.svg`"
                                                :alt="player.team_abbrev"
                                                class="h-12 w-12 object-contain"
                                                @error="
                                                    (e) =>
                                                        ((
                                                            e.target as HTMLImageElement
                                                        ).style.display =
                                                            'none')
                                                "
                                            />
                                            <div>
                                                <div
                                                    class="text-sm font-medium"
                                                >
                                                    {{ player.team_name }}
                                                </div>
                                                <div
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ player.team_abbrev }}
                                                </div>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        class="text-center font-semibold"
                                        >{{
                                            player.stats.games_played
                                        }}</TableCell
                                    >
                                    <TableCell
                                        class="text-center font-semibold"
                                        >{{ player.stats.goals }}</TableCell
                                    >
                                    <TableCell
                                        class="text-center font-semibold"
                                        >{{ player.stats.assists }}</TableCell
                                    >
                                    <TableCell
                                        class="text-center font-bold text-primary"
                                        >{{ player.stats.points }}</TableCell
                                    >
                                    <TableCell
                                        class="text-center"
                                        :class="{
                                            'text-green-600 dark:text-green-400':
                                                player.stats.plus_minus > 0,
                                            'text-red-600 dark:text-red-400':
                                                player.stats.plus_minus < 0,
                                        }"
                                    >
                                        {{
                                            player.stats.plus_minus > 0
                                                ? '+'
                                                : ''
                                        }}{{ player.stats.plus_minus }}
                                    </TableCell>
                                    <TableCell>{{
                                        player.selected_by?.name
                                    }}</TableCell>

                                    <TableCell
                                        v-if="pool.status === 'selection'"
                                        class="w-12"
                                    >
                                        <Button
                                            v-if="player.can_delete"
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-full text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950"
                                            @click="
                                                handleDeletePlayer(player.id)
                                            "
                                        >
                                            <X class="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <div v-else class="py-12 text-center text-muted-foreground">
                        <p>Aucun joueur sélectionné pour le moment</p>
                        <Button
                            v-if="pool.status === 'selection'"
                            @click="openSearchModal"
                            variant="outline"
                            class="mt-4 gap-2"
                        >
                            <UserPlus class="h-4 w-4" />
                            Rechercher un joueur
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Modals -->
        <PlayerSearchModal
            v-model:open="searchModalOpen"
            :pool-id="pool.id"
            @player-selected="handlePlayerSelected"
        />

        <PlayerDetailsModal
            v-model:open="detailsModalOpen"
            :player="selectedPlayer"
            :pool-id="pool.id"
            @player-added="handlePlayerAdded"
        />
    </AppLayout>
</template>
