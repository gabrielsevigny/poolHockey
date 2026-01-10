<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

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

interface Props {
    scorers: TopScorer[];
}

defineProps<Props>();

const getPositionLabel = (position: string): string => {
    const positions: Record<string, string> = {
        C: 'Centre',
        L: 'Ailier G',
        R: 'Ailier D',
        D: 'Défenseur',
    };
    return positions[position] || position;
};
</script>

<template>
    <Card>
        <CardHeader>
            <CardTitle class="text-xl">Top 5 Pointeurs NHL</CardTitle>
        </CardHeader>
        <CardContent class="p-0">
            <div class="divide-y">
                <div
                    v-for="(scorer, index) in scorers"
                    :key="scorer.id"
                    class="flex items-center gap-4 p-4 transition-colors hover:bg-accent/50"
                >
                    <!-- Rank -->
                    <div class="flex w-8 items-center justify-center">
                        <span
                            class="text-2xl font-bold"
                            :class="{
                                'text-yellow-500': index === 0,
                                'text-gray-400': index === 1,
                                'text-orange-600': index === 2,
                                'text-muted-foreground': index > 2,
                            }"
                        >
                            {{ index + 1 }}
                        </span>
                    </div>

                    <!-- Player Photo -->
                    <Avatar class="h-16 w-16 border-2">
                        <AvatarImage
                            :src="scorer.headshot_url"
                            :alt="scorer.full_name"
                        />
                        <AvatarFallback>
                            {{ scorer.first_name.charAt(0)
                            }}{{ scorer.last_name.charAt(0) }}
                        </AvatarFallback>
                    </Avatar>

                    <!-- Player Info -->
                    <div class="flex flex-1 flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold">
                                {{ scorer.full_name }}
                            </h3>
                            <Badge variant="outline" class="text-xs">
                                #{{ scorer.sweater_number }}
                            </Badge>
                        </div>
                        <div
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <span>{{ getPositionLabel(scorer.position) }}</span>
                            <span>•</span>
                            <span>{{ scorer.team_name }}</span>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="flex gap-6 text-center">
                        <div class="flex flex-col">
                            <span class="text-2xl font-bold">{{
                                scorer.points
                            }}</span>
                            <span class="text-xs text-muted-foreground"
                                >PTS</span
                            >
                        </div>
                        <div class="flex flex-col">
                            <span class="text-lg font-semibold">{{
                                scorer.goals
                            }}</span>
                            <span class="text-xs text-muted-foreground">B</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-lg font-semibold">{{
                                scorer.assists
                            }}</span>
                            <span class="text-xs text-muted-foreground">P</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-sm text-muted-foreground">{{
                                scorer.games_played
                            }}</span>
                            <span class="text-xs text-muted-foreground"
                                >PJ</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
