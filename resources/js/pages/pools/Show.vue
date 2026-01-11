<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

import PlayerDetailsModal from '@/components/PlayerDetailsModal.vue';
import PlayerSearchModal from '@/components/PlayerSearchModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Pool } from '@/types';
import { ChevronLeft, ChevronRight, FileText, Filter, Trash2, Trophy, UserPlus, Users, X } from 'lucide-vue-next';

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
        pim: number; // Minutes de pénalité
        pp_goals: number; // Buts en avantage numérique
        pp_points: number; // Points en avantage numérique
        sh_goals: number; // Buts en désavantage numérique
        sh_points: number; // Points en infériorité
        gw_goals: number; // Buts gagnants
        ot_goals: number; // Buts en prolongation
        shots: number; // Tirs
        avg_toi: number; // Temps de glace moyen (en secondes)
        shooting_pct?: number | null;
        faceoff_pct?: number | null;
        wins?: number; // For goalies
        shutouts?: number; // For goalies
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

interface Participant {
    id: number;
    name: string;
    email: string;
    total_goals: number;
    total_assists: number;
    total_points: number;
    total_plus_minus: number;
    active_players: number;
    injured_players: number;
    is_owner: boolean;
}

interface ExtendedPool extends Pool {
    selected_players: PoolPlayer[];
    is_admin: boolean;
    current_user_player_count: number;
    max_players_per_user: number;
    position_limits?: Record<string, PositionLimit> | null;
    position_counts?: Record<string, number>;
    participants?: Participant[];
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
const inviteParticipantModalOpen = ref(false);

// Top Players pagination
const topPlayersPerPage = ref(10);
const topPlayersCurrentPage = ref(1);

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
const sortBy = ref<string>(savedFilters?.sortBy ?? 'draft_order');
const filterByPosition = ref<string>(savedFilters?.filterByPosition ?? 'all');

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

// Format time on ice from seconds to MM:SS
const formatToi = (seconds: number): string => {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
};

// Get unique positions from selected players
const availablePositions = computed(() => {
    const positions = new Set(props.pool.selected_players?.map((p) => p.position) || []);
    return Array.from(positions).sort();
});

// Filtered and sorted players for "My Players" tab - only shows current user's players
const filteredPlayers = computed(() => {
    let players = [...(props.pool.selected_players || [])];

    // Always filter by current user in "My Players" tab
    if (currentUserId.value) {
        players = players.filter((p) => p.selected_by?.id === currentUserId.value);
    }

    // Filter by position
    if (filterByPosition.value !== 'all') {
        players = players.filter((p) => p.position === filterByPosition.value);
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
    if (!confirm('Êtes-vous sûr de vouloir retirer ce joueur de votre équipe?')) {
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
    sortBy.value = 'draft_order';
    filterByPosition.value = 'all';
};

const createQuickInvitation = async () => {
    try {
        await axios.post(`/pools/${props.pool.id}/invitations`, {});
        inviteParticipantModalOpen.value = false;
        router.visit(`/pools/${props.pool.id}/invitations`);
    } catch (error: any) {
        alert(error.response?.data?.message || 'Une erreur est survenue');
    }
};

const handleRemoveParticipant = async (participantId: number, participantName: string) => {
    if (!confirm(`Êtes-vous sûr de vouloir retirer ${participantName} de ce pool? Tous ses joueurs sélectionnés seront également retirés.`)) {
        return;
    }

    try {
        await axios.delete(`/pools/${props.pool.id}/participants/${participantId}`);
        router.reload({ only: ['pool'] });
    } catch (error: any) {
        alert(error.response?.data?.message || 'Une erreur est survenue');
    }
};

// Save filters to localStorage whenever they change
const saveFiltersToStorage = () => {
    try {
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({
                sortBy: sortBy.value,
                filterByPosition: filterByPosition.value,
            }),
        );
    } catch (e) {
        console.error('Error saving filters to storage:', e);
    }
};

// Check if current user has reached max players
const canAddMorePlayers = computed(() => props.pool.current_user_player_count < props.pool.max_players_per_user);

// Top Players sorted by points
const topPlayersSorted = computed(() => {
    const players = [...(props.pool.selected_players || [])];
    return players.sort((a, b) => b.stats.points - a.stats.points);
});

// Paginated top players
const topPlayersPaginated = computed(() => {
    const start = (topPlayersCurrentPage.value - 1) * topPlayersPerPage.value;
    const end = start + topPlayersPerPage.value;
    return topPlayersSorted.value.slice(start, end);
});

// Total pages for top players
const topPlayersTotalPages = computed(() => {
    return Math.ceil(topPlayersSorted.value.length / topPlayersPerPage.value);
});

// Reset to page 1 when per page changes
watch(topPlayersPerPage, () => {
    topPlayersCurrentPage.value = 1;
});

// Watch filter changes and save to localStorage
watch([sortBy, filterByPosition], () => {
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

                <Button v-if="pool.is_admin" class="gap-2" @click="inviteParticipantModalOpen = true">
                    <UserPlus class="h-4 w-4" />
                    Ajouter un participant
                </Button>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="border-b-1 pb-4">
                        <CardTitle>Dates</CardTitle>
                        <CardDescription>Informations du pool</CardDescription>
                    </CardHeader>
                    <CardContent class="grid grid-cols-2 gap-4 space-y-2">
                        <div>
                            <p class="text-sm text-muted-foreground">Repêchage</p>
                            <p class="font-semibold">{{ pool.start_date }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-muted-foreground">Fin du pool</p>
                            <p class="font-semibold">{{ pool.end_date }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="col-span-2">
                    <CardHeader class="border-b-1 pb-4">
                        <CardTitle>Règlements</CardTitle>
                        <CardDescription>{{ pool.rule_setting?.name }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4 text-sm">
                        <div v-if="pool.rule_setting">
                            <ul class="grid grid-cols-4 gap-4 gap-y-2">
                                <li class="flex">
                                    <div class="mr-1 font-bold">Buts:</div>
                                    <div class="text-muted-foreground">
                                        {{ pool.rule_setting.points_per_goal }}
                                        pts
                                    </div>
                                </li>
                                <li class="flex">
                                    <div class="mr-1 font-bold">Passes:</div>
                                    <div class="text-muted-foreground">
                                        {{ pool.rule_setting.points_per_assist }}
                                        pts
                                    </div>
                                </li>
                                <li v-if="pool.rule_setting.points_per_shutout" class="flex">
                                    <div class="mr-1 font-bold">Blanchissages:</div>
                                    <div class="text-muted-foreground">
                                        {{ pool.rule_setting.points_per_shutout }}
                                        pts
                                    </div>
                                </li>
                                <li v-if="pool.rule_setting.points_per_victory" class="flex">
                                    <div class="mr-1 font-bold">Victoires:</div>
                                    <div class="text-muted-foreground">
                                        {{ pool.rule_setting.points_per_victory }}
                                        pts
                                    </div>
                                </li>
                            </ul>
                        </div>

                        <!-- Position Limits -->
                        <div v-if="pool.position_limits" class="border-t pt-4">
                            <h4 class="mb-3 font-semibold">Limites par position</h4>
                            <div class="grid grid-cols-5 gap-2 space-y-2">
                                <div v-for="(limit, position) in pool.position_limits" :key="position" class="flex items-center justify-between rounded-lg border bg-muted/30 px-3 py-2">
                                    <div class="flex items-center gap-2">
                                        <Badge variant="secondary" class="text-xs">{{ position }}</Badge>
                                    </div>
                                    <div class="text-xs font-medium">
                                        <span :class="[pool.position_counts?.[position] >= limit.max ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground']"> {{ pool.position_counts?.[position] || 0 }}/{{ limit.max }} </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Tabs default-value="my-players" class="w-full">
                <TabsList class="grid w-full grid-cols-4">
                    <TabsTrigger value="my-players" class="gap-2">
                        <Trophy class="h-4 w-4" />
                        Mes Joueurs
                    </TabsTrigger>
                    <TabsTrigger value="top-players" class="gap-2">
                        <Trophy class="h-4 w-4" />
                        Top Joueurs NHL
                    </TabsTrigger>
                    <TabsTrigger value="participants" class="gap-2">
                        <Users class="h-4 w-4" />
                        Participants
                    </TabsTrigger>
                    <TabsTrigger value="configurations" class="gap-2">
                        <FileText class="h-4 w-4" />
                        Règlements
                    </TabsTrigger>
                </TabsList>

                <!-- Tab: My Players -->
                <TabsContent value="my-players">
                    <Card>
                        <CardHeader class="flex content-start items-start justify-between">
                            <div>
                                <CardTitle> Mes joueurs sélectionnés </CardTitle>
                                <CardDescription>
                                    {{ filteredPlayers.length }} joueur(s) dans votre équipe
                                    <span v-if="activeFiltersCount > 0" class="font-medium text-primary"> ({{ activeFiltersCount }} filtre{{ activeFiltersCount > 1 ? 's' : '' }} actif{{ activeFiltersCount > 1 ? 's' : '' }}) </span>
                                </CardDescription>
                            </div>

                            <div class="flex gap-2">
                                <!-- Filter Button -->
                                <Popover>
                                    <PopoverTrigger as-child>
                                        <Button variant="outline" class="relative gap-2">
                                            <Filter class="h-4 w-4" />
                                            Filtres
                                            <Badge v-if="activeFiltersCount > 0" class="ml-1 flex h-5 w-5 items-center justify-center rounded-full p-0 text-xs" variant="default">
                                                {{ activeFiltersCount }}
                                            </Badge>
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent class="w-80" align="end">
                                        <div class="space-y-4">
                                            <div class="space-y-2">
                                                <h4 class="leading-none font-medium">Filtres et tri</h4>
                                                <p class="text-sm text-muted-foreground">Personnalisez l'affichage des joueurs</p>
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
                                                        <SelectItem value="all"> Toutes les positions </SelectItem>
                                                        <SelectItem v-for="position in availablePositions" :key="position" :value="position">
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
                                                        <SelectItem value="draft_order"> Ordre de sélection </SelectItem>
                                                        <SelectItem value="points"> Points (PTS) </SelectItem>
                                                        <SelectItem value="goals"> Buts (B) </SelectItem>
                                                        <SelectItem value="assists"> Passes (A) </SelectItem>
                                                        <SelectItem value="games_played"> Matchs joués (MJ) </SelectItem>
                                                        <SelectItem value="plus_minus"> Différentiel (+/-) </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <Separator />

                                            <!-- Reset Button -->
                                            <Button variant="outline" class="w-full" @click="resetFilters" :disabled="activeFiltersCount === 0"> Réinitialiser les filtres </Button>
                                        </div>
                                    </PopoverContent>
                                </Popover>

                                <!-- Add Player Button -->
                                <Button v-if="pool.status === 'selection'" @click="openSearchModal" variant="outline" class="gap-2" :disabled="!canAddMorePlayers">
                                    <UserPlus class="h-4 w-4" />
                                    Rechercher un joueur
                                    <span v-if="!canAddMorePlayers" class="ml-2 text-xs">({{ pool.current_user_player_count }}/{{ pool.max_players_per_user }})</span>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="filteredPlayers.length > 0" class="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead class="w-12">#</TableHead>
                                            <TableHead>Joueur</TableHead>
                                            <TableHead class="text-center">POS</TableHead>
                                            <TableHead>Équipe</TableHead>
                                            <TableHead class="text-center">MJ</TableHead>
                                            <TableHead class="text-center">B</TableHead>
                                            <TableHead class="text-center">A</TableHead>
                                            <TableHead class="text-center">PTS</TableHead>
                                            <TableHead class="text-center">+/-</TableHead>
                                            <TableHead class="text-center">Pun</TableHead>
                                            <TableHead class="text-center">Ban</TableHead>
                                            <TableHead class="text-center">Pan</TableHead>
                                            <TableHead class="text-center">Bin</TableHead>
                                            <TableHead class="text-center">Pin</TableHead>
                                            <TableHead class="text-center">TG/MJ</TableHead>
                                            <TableHead class="text-center">BG</TableHead>
                                            <TableHead class="text-center">Bpr</TableHead>
                                            <TableHead class="text-center">T</TableHead>
                                            <TableHead class="text-center">% tir</TableHead>
                                            <TableHead class="text-center">% M.J.</TableHead>
                                            <TableHead v-if="pool.status === 'selection'" class="w-12"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="(player, index) in filteredPlayers"
                                            :key="player.id"
                                            :class="{
                                                'bg-green-50 dark:bg-green-950/20': player.stats.plus_minus > 0,
                                                'bg-red-50 dark:bg-red-950/20': player.stats.plus_minus < 0,
                                            }"
                                        >
                                            <TableCell class="font-bold">{{ index + 1 }}</TableCell>
                                            <TableCell>
                                                <div class="flex items-center gap-3">
                                                    <img
                                                        :src="player.headshot_url"
                                                        :alt="player.player_name"
                                                        class="h-12 w-12 rounded-full border-2 border-white bg-gradient-to-br from-gray-100 to-gray-200 object-cover shadow-md dark:border-gray-700 dark:from-gray-800 dark:to-gray-900"
                                                        @error="(e) => ((e.target as HTMLImageElement).src = '/favicon.svg')"
                                                    />
                                                    <span class="font-semibold">{{ player.player_name }}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <Badge variant="secondary" class="text-xs">{{ player.position }}</Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div class="flex items-center gap-2">
                                                    <img
                                                        :src="`https://assets.nhle.com/logos/nhl/svg/${player.team_abbrev}_light.svg`"
                                                        :alt="player.team_abbrev"
                                                        class="h-12 w-12 object-contain"
                                                        @error="(e) => ((e.target as HTMLImageElement).style.display = 'none')"
                                                    />
                                                    <div>
                                                        <div class="text-sm font-medium">
                                                            {{ player.team_name }}
                                                        </div>
                                                        <div class="text-xs text-muted-foreground">
                                                            {{ player.team_abbrev }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell class="text-center">{{ player.stats.games_played }}</TableCell>
                                            <TableCell class="text-center">{{ player.stats.goals }}</TableCell>
                                            <TableCell class="text-center">{{ player.stats.assists }}</TableCell>
                                            <TableCell class="text-center">{{ player.stats.points }}</TableCell>
                                            <TableCell
                                                class="text-center"
                                                :class="{
                                                    'text-green-600 dark:text-green-400': player.stats.plus_minus > 0,
                                                    'text-red-600 dark:text-red-400': player.stats.plus_minus < 0,
                                                }"
                                            >
                                                {{ player.stats.plus_minus > 0 ? '+' : '' }}{{ player.stats.plus_minus }}
                                            </TableCell>
                                            <TableCell class="text-center">{{ player.stats.pim }}</TableCell>
                                            <TableCell class="text-center">{{ player.stats.pp_goals }}</TableCell>
                                            <TableCell class="text-center">{{ player.stats.pp_points }}</TableCell>
                                            <TableCell class="text-center">{{ player.stats.sh_goals }}</TableCell>
                                            <TableCell class="text-center">{{ player.stats.sh_points }}</TableCell>
                                            <TableCell class="text-center">{{ formatToi(player.stats.avg_toi) }}</TableCell>
                                            <TableCell class="text-center">{{ player.stats.gw_goals }}</TableCell>
                                            <TableCell class="text-center">{{ player.stats.ot_goals }}</TableCell>
                                            <TableCell class="text-center">{{ player.stats.shots }}</TableCell>
                                            <TableCell class="text-center">{{ (player.stats.shooting_pct ?? 0).toFixed(1) }}%</TableCell>
                                            <TableCell class="text-center">{{ (player.stats.faceoff_pct ?? 0).toFixed(1) }}%</TableCell>

                                            <TableCell v-if="pool.status === 'selection'" class="w-12">
                                                <Button v-if="player.can_delete" variant="ghost" size="icon" class="h-8 w-8 rounded-full text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950" @click="handleDeletePlayer(player.id)">
                                                    <X class="h-4 w-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center rounded-lg border border-dashed py-16">
                                <div class="flex h-20 w-20 items-center justify-center rounded-full bg-muted">
                                    <Trophy class="h-10 w-10 text-muted-foreground" />
                                </div>
                                <h3 class="mt-4 text-lg font-semibold">Aucun joueur sélectionné</h3>
                                <p class="mt-2 text-sm text-muted-foreground">Commencez à construire votre équipe en ajoutant des joueurs</p>
                                <Button v-if="pool.status === 'selection' && canAddMorePlayers" @click="openSearchModal" class="mt-6 gap-2">
                                    <UserPlus class="h-4 w-4" />
                                    Ajouter un joueur
                                </Button>
                                <p v-else-if="pool.status !== 'selection'" class="mt-4 text-sm text-muted-foreground italic">La période de sélection est terminée</p>
                                <p v-else-if="!canAddMorePlayers" class="mt-4 text-sm text-muted-foreground italic">Vous avez atteint le nombre maximum de joueurs</p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Tab: Top Players NHL -->
                <TabsContent value="top-players">
                    <Card>
                        <CardHeader>
                            <CardTitle>Top Joueurs NHL du Pool</CardTitle>
                            <CardDescription> Classement des {{ topPlayersSorted.length }} meilleurs joueurs selon leurs performances </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="topPlayersSorted.length > 0" class="space-y-4">
                                <!-- Controls -->
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <Label for="per-page" class="text-sm">Afficher</Label>
                                        <Select v-model="topPlayersPerPage">
                                            <SelectTrigger id="per-page" class="w-20">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem :value="10">10</SelectItem>
                                                <SelectItem :value="25">25</SelectItem>
                                                <SelectItem :value="50">50</SelectItem>
                                                <SelectItem :value="100">100</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <span class="text-sm text-muted-foreground">joueurs par page</span>
                                    </div>

                                    <div class="text-sm text-muted-foreground">
                                        Affichage de {{ (topPlayersCurrentPage - 1) * topPlayersPerPage + 1 }} à
                                        {{ Math.min(topPlayersCurrentPage * topPlayersPerPage, topPlayersSorted.length) }}
                                        sur {{ topPlayersSorted.length }} joueurs
                                    </div>
                                </div>

                                <!-- Table -->
                                <div class="rounded-md border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead class="w-12">#</TableHead>
                                                <TableHead>Joueur</TableHead>
                                                <TableHead class="text-center">POS</TableHead>
                                                <TableHead>Équipe</TableHead>
                                                <TableHead class="text-center">MJ</TableHead>
                                                <TableHead class="text-center">B</TableHead>
                                                <TableHead class="text-center">A</TableHead>
                                                <TableHead class="text-center">PTS</TableHead>
                                                <TableHead class="text-center">+/-</TableHead>
                                                <TableHead class="text-center">Pun</TableHead>
                                                <TableHead class="text-center">Ban</TableHead>
                                                <TableHead class="text-center">Pan</TableHead>
                                                <TableHead class="text-center">Bin</TableHead>
                                                <TableHead class="text-center">Pin</TableHead>
                                                <TableHead class="text-center">TG/MJ</TableHead>
                                                <TableHead class="text-center">BG</TableHead>
                                                <TableHead class="text-center">Bpr</TableHead>
                                                <TableHead class="text-center">T</TableHead>
                                                <TableHead class="text-center">% tir</TableHead>
                                                <TableHead class="text-center">% M.J.</TableHead>
                                                <TableHead>Sélectionné par</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            <TableRow
                                                v-for="(player, index) in topPlayersPaginated"
                                                :key="player.id"
                                                :class="{
                                                    'bg-yellow-50 dark:bg-yellow-950/20': index === 0 && topPlayersCurrentPage === 1,
                                                    'bg-gray-50 dark:bg-gray-950/20': index === 1 && topPlayersCurrentPage === 1,
                                                    'bg-orange-50 dark:bg-orange-950/20': index === 2 && topPlayersCurrentPage === 1,
                                                }"
                                            >
                                                <TableCell class="font-bold">
                                                    {{ (topPlayersCurrentPage - 1) * topPlayersPerPage + index + 1 }}
                                                </TableCell>
                                                <TableCell>
                                                    <div class="flex items-center gap-3">
                                                        <img
                                                            :src="player.headshot_url"
                                                            :alt="player.player_name"
                                                            class="h-12 w-12 rounded-full border-2 border-white bg-gradient-to-br from-gray-100 to-gray-200 object-cover shadow-md dark:border-gray-700 dark:from-gray-800 dark:to-gray-900"
                                                            @error="(e) => ((e.target as HTMLImageElement).src = '/favicon.svg')"
                                                        />
                                                        <span class="font-semibold">{{ player.player_name }}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell class="text-center">
                                                    <Badge variant="secondary" class="text-xs">{{ player.position }}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div class="flex items-center gap-2">
                                                        <img
                                                            :src="`https://assets.nhle.com/logos/nhl/svg/${player.team_abbrev}_light.svg`"
                                                            :alt="player.team_abbrev"
                                                            class="h-8 w-8 object-contain"
                                                            @error="(e) => ((e.target as HTMLImageElement).style.display = 'none')"
                                                        />
                                                        <span class="text-sm">{{ player.team_abbrev }}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell class="text-center font-semibold">{{ player.stats.games_played }}</TableCell>
                                                <TableCell class="text-center font-semibold">{{ player.stats.goals }}</TableCell>
                                                <TableCell class="text-center font-semibold">{{ player.stats.assists }}</TableCell>
                                                <TableCell class="text-center font-bold text-primary">{{ player.stats.points }}</TableCell>
                                                <TableCell
                                                    class="text-center"
                                                    :class="{
                                                        'text-green-600 dark:text-green-400': player.stats.plus_minus > 0,
                                                        'text-red-600 dark:text-red-400': player.stats.plus_minus < 0,
                                                    }"
                                                >
                                                    {{ player.stats.plus_minus > 0 ? '+' : '' }}{{ player.stats.plus_minus }}
                                                </TableCell>
                                                <TableCell class="text-center">{{ player.stats.pim }}</TableCell>
                                                <TableCell class="text-center">{{ player.stats.pp_goals }}</TableCell>
                                                <TableCell class="text-center">{{ player.stats.pp_points }}</TableCell>
                                                <TableCell class="text-center">{{ player.stats.sh_goals }}</TableCell>
                                                <TableCell class="text-center">{{ player.stats.sh_points }}</TableCell>
                                                <TableCell class="text-center">{{ formatToi(player.stats.avg_toi) }}</TableCell>
                                                <TableCell class="text-center">{{ player.stats.gw_goals }}</TableCell>
                                                <TableCell class="text-center">{{ player.stats.ot_goals }}</TableCell>
                                                <TableCell class="text-center">{{ player.stats.shots }}</TableCell>
                                                <TableCell class="text-center">{{ (player.stats.shooting_pct ?? 0).toFixed(1) }}%</TableCell>
                                                <TableCell class="text-center">{{ (player.stats.faceoff_pct ?? 0).toFixed(1) }}%</TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{{ player.selected_by?.name }}</Badge>
                                                </TableCell>
                                            </TableRow>
                                        </TableBody>
                                    </Table>
                                </div>

                                <!-- Pagination -->
                                <div class="flex items-center justify-between">
                                    <div class="text-sm text-muted-foreground">Page {{ topPlayersCurrentPage }} sur {{ topPlayersTotalPages }}</div>
                                    <div class="flex gap-2">
                                        <Button variant="outline" size="sm" :disabled="topPlayersCurrentPage === 1" @click="topPlayersCurrentPage--">
                                            <ChevronLeft class="h-4 w-4" />
                                            Précédent
                                        </Button>
                                        <Button variant="outline" size="sm" :disabled="topPlayersCurrentPage === topPlayersTotalPages" @click="topPlayersCurrentPage++">
                                            Suivant
                                            <ChevronRight class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="py-12 text-center text-muted-foreground">
                                <p>Aucun joueur sélectionné pour le moment</p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Tab: Participants -->
                <TabsContent value="participants">
                    <Card>
                        <CardHeader>
                            <CardTitle>Participants du Pool</CardTitle>
                            <CardDescription> Classement des {{ pool.participants?.length || 0 }} participants avec leurs statistiques cumulées </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="pool.participants && pool.participants.length > 0" class="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead class="w-12">#</TableHead>
                                            <TableHead>Nom</TableHead>
                                            <TableHead class="text-center">Joueurs</TableHead>
                                            <TableHead class="text-center">B</TableHead>
                                            <TableHead class="text-center">A</TableHead>
                                            <TableHead class="text-center">PTS</TableHead>
                                            <TableHead class="text-center">+/-</TableHead>
                                            <TableHead v-if="pool.is_admin" class="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow
                                            v-for="(participant, index) in pool.participants"
                                            :key="participant.id"
                                            :class="{
                                                'bg-amber-50 dark:bg-amber-950/20': participant.is_owner,
                                            }"
                                        >
                                            <TableCell class="font-bold">{{ index + 1 }}</TableCell>
                                            <TableCell>
                                                <div class="flex items-center gap-2">
                                                    <span class="font-semibold">{{ participant.name }}</span>
                                                    <Badge v-if="participant.is_owner" variant="secondary" class="text-xs"> Propriétaire </Badge>
                                                </div>
                                                <div class="text-xs text-muted-foreground">{{ participant.email }}</div>
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <Badge variant="outline" class="font-semibold">{{ participant.active_players }}</Badge>
                                            </TableCell>
                                            <TableCell class="text-center font-semibold">
                                                {{ participant.total_goals }}
                                            </TableCell>
                                            <TableCell class="text-center font-semibold">
                                                {{ participant.total_assists }}
                                            </TableCell>
                                            <TableCell class="text-center">
                                                <div class="text-lg font-bold text-primary">{{ participant.total_points }}</div>
                                            </TableCell>
                                            <TableCell
                                                class="text-center font-semibold"
                                                :class="{
                                                    'text-green-600 dark:text-green-400': participant.total_plus_minus > 0,
                                                    'text-red-600 dark:text-red-400': participant.total_plus_minus < 0,
                                                }"
                                            >
                                                {{ participant.total_plus_minus > 0 ? '+' : '' }}{{ participant.total_plus_minus }}
                                            </TableCell>
                                            <TableCell v-if="pool.is_admin" class="text-right">
                                                <div class="flex justify-end gap-2">
                                                    <Button
                                                        v-if="!participant.is_owner"
                                                        variant="outline"
                                                        size="sm"
                                                        class="gap-2 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950"
                                                        @click="handleRemoveParticipant(participant.id, participant.name)"
                                                    >
                                                        <Trash2 class="h-4 w-4" />
                                                        Retirer
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                            <div v-else class="py-12 text-center text-muted-foreground">
                                <p>Aucun participant pour le moment</p>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <!-- Tab: Configurations (Règlements) -->
                <TabsContent value="configurations">
                    <Card>
                        <CardHeader>
                            <CardTitle>Règlements du Pool</CardTitle>
                            <CardDescription> Sommaire complet des règles et configurations de {{ pool.name }} </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <!-- Section: Informations générales -->
                            <div class="space-y-3">
                                <h3 class="text-lg font-semibold">Informations générales</h3>
                                <div class="rounded-lg border bg-muted/30 p-4">
                                    <div class="grid gap-4 md:grid-cols-3">
                                        <div>
                                            <p class="text-sm font-medium text-muted-foreground">Nom du pool</p>
                                            <p class="mt-1 text-base font-semibold">{{ pool.name }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-muted-foreground">Date de repêchage</p>
                                            <p class="mt-1 text-base font-semibold">{{ pool.start_date }}</p>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-muted-foreground">Date de fin</p>
                                            <p class="mt-1 text-base font-semibold">{{ pool.end_date }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <Separator />

                            <!-- Section: Système de pointage -->
                            <div v-if="pool.rule_setting" class="space-y-3">
                                <h3 class="text-lg font-semibold">Système de pointage</h3>
                                <div class="rounded-lg border bg-muted/30 p-4">
                                    <p class="mb-4 text-sm text-muted-foreground">
                                        Configuration: <span class="font-semibold text-foreground">{{ pool.rule_setting.name }}</span>
                                    </p>
                                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                        <div class="flex items-center gap-3 rounded-lg border bg-background p-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900">
                                                <span class="text-lg font-bold text-blue-600 dark:text-blue-300">B</span>
                                            </div>
                                            <div>
                                                <p class="text-xs text-muted-foreground">Buts</p>
                                                <p class="text-xl font-bold">{{ pool.rule_setting.points_per_goal }} pts</p>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-3 rounded-lg border bg-background p-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                                                <span class="text-lg font-bold text-green-600 dark:text-green-300">A</span>
                                            </div>
                                            <div>
                                                <p class="text-xs text-muted-foreground">Passes</p>
                                                <p class="text-xl font-bold">{{ pool.rule_setting.points_per_assist }} pts</p>
                                            </div>
                                        </div>
                                        <div v-if="pool.rule_setting.points_per_shutout" class="flex items-center gap-3 rounded-lg border bg-background p-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900">
                                                <span class="text-lg font-bold text-purple-600 dark:text-purple-300">BL</span>
                                            </div>
                                            <div>
                                                <p class="text-xs text-muted-foreground">Blanchissages</p>
                                                <p class="text-xl font-bold">{{ pool.rule_setting.points_per_shutout }} pts</p>
                                            </div>
                                        </div>
                                        <div v-if="pool.rule_setting.points_per_victory" class="flex items-center gap-3 rounded-lg border bg-background p-3">
                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900">
                                                <span class="text-lg font-bold text-amber-600 dark:text-amber-300">V</span>
                                            </div>
                                            <div>
                                                <p class="text-xs text-muted-foreground">Victoires</p>
                                                <p class="text-xl font-bold">{{ pool.rule_setting.points_per_victory }} pts</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <Separator />

                            <!-- Section: Limites de joueurs -->
                            <div class="space-y-3">
                                <h3 class="text-lg font-semibold">Limites de joueurs</h3>
                                <div class="rounded-lg border bg-muted/30 p-4">
                                    <div class="mb-4">
                                        <p class="text-sm text-muted-foreground">Joueurs maximum par participant</p>
                                        <p class="mt-1 text-2xl font-bold text-primary">{{ pool.max_players_per_user }} joueurs</p>
                                    </div>

                                    <!-- Position limits if available -->
                                    <div v-if="pool.position_limits" class="mt-6">
                                        <p class="mb-3 text-sm font-medium">Limites par position</p>
                                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                                            <div v-for="(limit, position) in pool.position_limits" :key="position" class="flex items-center justify-between rounded-lg border bg-background p-3">
                                                <Badge variant="secondary" class="text-sm font-bold">{{ position }}</Badge>
                                                <div class="text-right">
                                                    <p class="text-xs text-muted-foreground">Min - Max</p>
                                                    <p class="text-base font-bold">{{ limit.min }} - {{ limit.max }}</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <Separator />

                            <!-- Section: Statut du pool -->
                            <div class="space-y-3">
                                <h3 class="text-lg font-semibold">Statut actuel</h3>
                                <div class="rounded-lg border bg-muted/30 p-4">
                                    <div class="flex items-center gap-3">
                                        <Badge :class="statusClasses[pool.status]" class="px-4 py-2 text-base">
                                            {{ statusLabels[pool.status] }}
                                        </Badge>
                                        <p class="text-sm text-muted-foreground">{{ pool.participants?.length || 0 }} participant(s) · {{ pool.selected_players?.length || 0 }} joueur(s) sélectionné(s)</p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>

        <!-- Modals -->
        <PlayerSearchModal v-model:open="searchModalOpen" :pool-id="pool.id" @player-selected="handlePlayerSelected" />

        <PlayerDetailsModal v-model:open="detailsModalOpen" :player="selectedPlayer" :pool-id="pool.id" @player-added="handlePlayerAdded" />

        <!-- Invite Participant Modal -->
        <Dialog v-model:open="inviteParticipantModalOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Ajouter un participant</DialogTitle>
                    <DialogDescription> Gérez les invitations pour votre pool </DialogDescription>
                </DialogHeader>
                <div class="flex flex-col gap-4">
                    <Button class="w-full justify-start" @click="router.visit(`/pools/${pool.id}/invitations`)">
                        <Users class="mr-2 h-4 w-4" />
                        Voir toutes les invitations
                    </Button>
                    <Separator />
                    <p class="text-sm text-muted-foreground">Ou créez une nouvelle invitation rapidement</p>
                    <Button @click="createQuickInvitation" class="w-full">
                        <UserPlus class="mr-2 h-4 w-4" />
                        Créer une invitation
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
