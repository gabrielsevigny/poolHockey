<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import axios from 'axios';
import { Info, Search } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref, watch } from 'vue';

interface Player {
    id: number;
    full_name: string;
    position: string;
    team_abbrev: string;
    team_name: string;
    headshot_url: string;
    points: number;
    goals: number;
    assists: number;
    games_played: number;
    games_in_pool: number;
    is_available: boolean;
    selected_by: string | null;
}

interface Props {
    open: boolean;
    poolId: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    'player-selected': [player: Player];
}>();

const searchQuery = ref('');
const searchResults = ref<Player[]>([]);
const isSearching = ref(false);
const searchTimeout = ref<NodeJS.Timeout | null>(null);

// Watch for search query changes
watch(searchQuery, (newQuery) => {
    if (searchTimeout.value) {
        clearTimeout(searchTimeout.value);
    }

    if (newQuery.length < 2) {
        searchResults.value = [];
        isSearching.value = false;
        return;
    }

    isSearching.value = true;
    searchTimeout.value = setTimeout(() => {
        performSearch(newQuery);
    }, 500);
});

const performSearch = async (query: string) => {
    isSearching.value = true;

    try {
        const response = await axios.get(
            `/pools/${props.poolId}/players/search`,
            {
                params: { query },
            },
        );

        searchResults.value = response.data.data;
    } catch (error) {
        console.error('Error searching players:', error);
        searchResults.value = [];
    } finally {
        isSearching.value = false;
    }
};

const handlePlayerClick = (player: Player) => {
    if (!player.is_available) {
        return;
    }

    emit('player-selected', player);
};

const handleOpenChange = (value: boolean) => {
    if (!value) {
        searchQuery.value = '';
        searchResults.value = [];
        isSearching.value = false;
        if (searchTimeout.value) {
            clearTimeout(searchTimeout.value);
        }
    }
    emit('update:open', value);
};

// Real-time updates via Laravel Echo
onMounted(() => {
    if (window.Echo) {
        window.Echo.channel(`pool.${props.poolId}`)
            .listen('.player.selected', (event: {
                nhl_player_id: number;
                player_name: string;
                selected_by: string;
            }) => {
                // Mark player as unavailable in search results
                searchResults.value = searchResults.value.map((player) => {
                    if (player.id === event.nhl_player_id) {
                        return {
                            ...player,
                            is_available: false,
                            selected_by: event.selected_by,
                        };
                    }
                    return player;
                });
            })
            .listen('.player.removed', (event: { nhl_player_id: number }) => {
                // Mark player as available again
                searchResults.value = searchResults.value.map((player) => {
                    if (player.id === event.nhl_player_id) {
                        return {
                            ...player,
                            is_available: true,
                            selected_by: null,
                        };
                    }
                    return player;
                });
            });
    }
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave(`pool.${props.poolId}`);
    }
});
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-h-[80vh] sm:max-w-[700px]">
            <DialogHeader>
                <DialogTitle>Rechercher un joueur</DialogTitle>
                <DialogDescription>
                    Recherchez et sélectionnez des joueurs de la NHL pour votre
                    équipe.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <div class="relative">
                    <Search
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="searchQuery"
                        placeholder="Rechercher par nom de joueur..."
                        class="pl-10"
                    />
                </div>

                <div class="max-h-[400px] space-y-2 overflow-y-auto">
                    <!-- Loading state -->
                    <div
                        v-if="isSearching && searchQuery.length >= 2"
                        class="space-y-2"
                    >
                        <div
                            v-for="i in 3"
                            :key="i"
                            class="flex items-center space-x-3 rounded-lg border p-3"
                        >
                            <Skeleton class="h-12 w-12 rounded-full" />
                            <div class="flex-1 space-y-2">
                                <Skeleton class="h-4 w-[200px]" />
                                <Skeleton class="h-3 w-[150px]" />
                            </div>
                        </div>
                    </div>

                    <!-- No results -->
                    <div
                        v-else-if="
                            !isSearching &&
                            searchQuery.length >= 2 &&
                            searchResults.length === 0
                        "
                        class="py-8 text-center text-muted-foreground"
                    >
                        Aucun joueur trouvé
                    </div>

                    <!-- Search results -->
                    <template
                        v-else-if="!isSearching && searchResults.length > 0"
                    >
                        <div
                            v-for="player in searchResults"
                            :key="player.id"
                            :class="[
                                'flex items-center space-x-3 rounded-lg border p-3 transition-colors',
                                player.is_available
                                    ? 'cursor-pointer hover:bg-accent'
                                    : 'cursor-not-allowed opacity-60',
                            ]"
                            @click="handlePlayerClick(player)"
                        >
                            <img
                                :src="player.headshot_url"
                                :alt="player.full_name"
                                class="h-12 w-12 rounded-full bg-muted object-cover"
                                @error="
                                    (e) =>
                                        ((e.target as HTMLImageElement).src =
                                            '/favicon.svg')
                                "
                            />

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate font-semibold">
                                        {{ player.full_name }}
                                    </p>
                                    <Badge variant="secondary" class="text-xs">
                                        {{ player.position }}
                                    </Badge>
                                    <Badge
                                        v-if="!player.is_available"
                                        variant="destructive"
                                        class="text-xs"
                                    >
                                        Indisponible
                                    </Badge>
                                </div>
                                <div
                                    class="mt-1 flex items-center gap-2 text-sm text-muted-foreground"
                                >
                                    <span>{{ player.team_name }}</span>
                                    <span>•</span>
                                    <span
                                        >{{ player.games_in_pool }} matchs dans
                                        ce pool</span
                                    >
                                </div>
                                <div
                                    v-if="!player.is_available"
                                    class="mt-1 text-xs text-destructive"
                                >
                                    Sélectionné par {{ player.selected_by }}
                                </div>
                                <div
                                    class="mt-1 flex items-center gap-3 text-xs text-muted-foreground"
                                >
                                    <span>{{ player.points }} PTS</span>
                                    <span>{{ player.goals }} B</span>
                                    <span>{{ player.assists }} A</span>
                                    <span>{{ player.games_played }} GP</span>
                                </div>
                            </div>

                            <Button
                                v-if="player.is_available"
                                variant="ghost"
                                size="sm"
                                @click.stop="emit('player-selected', player)"
                            >
                                <Info class="h-4 w-4" />
                            </Button>
                        </div>
                    </template>

                    <!-- Empty state -->
                    <div
                        v-else-if="searchQuery.length < 2"
                        class="py-8 text-center text-muted-foreground"
                    >
                        Entrez au moins 2 caractères pour rechercher
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
