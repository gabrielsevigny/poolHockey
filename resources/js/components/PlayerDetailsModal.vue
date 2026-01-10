<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Spinner } from '@/components/ui/spinner';
import axios from 'axios';
import { ref } from 'vue';

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
    plus_minus?: number;
    penalty_minutes?: number;
    shots?: number;
    shooting_pct?: number;
    is_available: boolean;
    selected_by: string | null;
}

interface Props {
    open: boolean;
    player: Player | null;
    poolId: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
    'player-added': [];
}>();

const isSubmitting = ref(false);

const handleAddPlayer = async () => {
    if (!props.player || !props.player.is_available) {
        return;
    }

    isSubmitting.value = true;

    try {
        await axios.post(`/pools/${props.poolId}/players`, {
            nhl_player_id: props.player.id,
            player_name: props.player.full_name,
            position: props.player.position,
            team_abbrev: props.player.team_abbrev,
            team_name: props.player.team_name,
            headshot_url: props.player.headshot_url,
        });

        emit('player-added');
        emit('update:open', false);
    } catch (error: any) {
        console.error('Error adding player:', error);
        alert(error.response?.data?.message || 'Une erreur est survenue');
    } finally {
        isSubmitting.value = false;
    }
};

const handleOpenChange = (value: boolean) => {
    emit('update:open', value);
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent v-if="player" class="sm:max-w-[600px]">
            <DialogHeader>
                <DialogTitle>Détails du joueur</DialogTitle>
                <DialogDescription>
                    Statistiques de la saison en cours
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4">
                <!-- Player Header -->
                <div class="flex items-start space-x-4">
                    <img
                        :src="player.headshot_url"
                        :alt="player.full_name"
                        class="h-24 w-24 rounded-full border-2 bg-muted object-cover"
                        @error="
                            (e) =>
                                ((e.target as HTMLImageElement).src =
                                    '/favicon.svg')
                        "
                    />
                    <div class="flex-1">
                        <h3 class="text-2xl font-bold">
                            {{ player.full_name }}
                        </h3>
                        <div class="mt-2 flex items-center gap-2">
                            <Badge variant="secondary">{{
                                player.position
                            }}</Badge>
                            <Badge
                                v-if="!player.is_available"
                                variant="destructive"
                            >
                                Indisponible
                            </Badge>
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ player.team_name }} ({{ player.team_abbrev }})
                        </p>
                        <p class="mt-1 text-sm font-semibold text-primary">
                            {{ player.games_in_pool }} matchs dans ce pool
                        </p>
                        <p
                            v-if="!player.is_available"
                            class="mt-1 text-sm text-destructive"
                        >
                            Sélectionné par {{ player.selected_by }}
                        </p>
                    </div>
                </div>

                <Separator />

                <!-- Stats Grid -->
                <div class="grid grid-cols-2 gap-3">
                    <Card>
                        <CardContent class="p-4">
                            <p class="text-sm text-muted-foreground">Points</p>
                            <p class="text-3xl font-bold">
                                {{ player.points }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="p-4">
                            <p class="text-sm text-muted-foreground">
                                Matchs joués
                            </p>
                            <p class="text-3xl font-bold">
                                {{ player.games_played }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="p-4">
                            <p class="text-sm text-muted-foreground">Buts</p>
                            <p class="text-3xl font-bold">{{ player.goals }}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent class="p-4">
                            <p class="text-sm text-muted-foreground">Passes</p>
                            <p class="text-3xl font-bold">
                                {{ player.assists }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card v-if="player.plus_minus !== undefined">
                        <CardContent class="p-4">
                            <p class="text-sm text-muted-foreground">+/-</p>
                            <p class="text-3xl font-bold">
                                {{ player.plus_minus > 0 ? '+' : ''
                                }}{{ player.plus_minus }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card v-if="player.penalty_minutes !== undefined">
                        <CardContent class="p-4">
                            <p class="text-sm text-muted-foreground">
                                Minutes de pénalité
                            </p>
                            <p class="text-3xl font-bold">
                                {{ player.penalty_minutes }}
                            </p>
                        </CardContent>
                    </Card>

                    <Card v-if="player.shots !== undefined">
                        <CardContent class="p-4">
                            <p class="text-sm text-muted-foreground">Tirs</p>
                            <p class="text-3xl font-bold">{{ player.shots }}</p>
                        </CardContent>
                    </Card>

                    <Card v-if="player.shooting_pct !== undefined">
                        <CardContent class="p-4">
                            <p class="text-sm text-muted-foreground">% Tirs</p>
                            <p class="text-3xl font-bold">
                                {{ player.shooting_pct.toFixed(1) }}%
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <DialogFooter>
                <Button
                    variant="outline"
                    @click="handleOpenChange(false)"
                    :disabled="isSubmitting"
                >
                    Annuler
                </Button>
                <Button
                    @click="handleAddPlayer"
                    :disabled="!player.is_available || isSubmitting"
                >
                    <Spinner v-if="isSubmitting" class="mr-2 h-4 w-4" />
                    {{ isSubmitting ? 'Ajout...' : 'Ajouter à mon équipe' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
