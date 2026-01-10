<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Plus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/* --------------------------------- TYPES --------------------------------- */

interface ScoringRule {
    type: string;
    label: string;
    points: number;
}

interface Props {
    modelValue?: {
        scoring_rules: ScoringRule[];
        player_limits: {
            max_per_user: number;
            by_position?: Record<string, { min: number; max: number }>;
        };
    };
}

const props = defineProps<Props>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: Props['modelValue']): void;
}>();

/* ------------------------------ OPTIONS DATA ------------------------------ */

const availableStatTypes = [
    { value: 'goal', label: 'But' },
    { value: 'assist', label: 'Passe' },
    { value: 'shutout', label: 'Blanchissage' },
    { value: 'victory', label: 'Victoire' },
    { value: 'overtime_loss', label: 'Défaite en prolongation' },
    { value: 'power_play_goal', label: 'But en supériorité' },
    { value: 'short_handed_goal', label: 'But en infériorité' },
    { value: 'game_winning_goal', label: 'But gagnant' },
    { value: 'hat_trick', label: 'Tour du chapeau' },
];

/* --------------------------------- STATE --------------------------------- */

const scoringRules = ref<ScoringRule[]>([]);
const selectedStatType = ref('');
const maxPlayersPerUser = ref(20);

const isLimitedPlayers = ref(false);
const availablePositions = [
    { value: 'C', label: 'Centre', shortLabel: 'C' },
    { value: 'L', label: 'Ailier gauche', shortLabel: 'L' },
    { value: 'R', label: 'Ailier droit', shortLabel: 'R' },
    { value: 'D', label: 'Défenseur', shortLabel: 'D' },
    { value: 'G', label: 'Gardien', shortLabel: 'G' },
];

const positionLimits = ref<Record<string, { min: number; max: number }>>({
    C: { min: 0, max: 0 },
    L: { min: 0, max: 0 },
    R: { min: 0, max: 0 },
    D: { min: 0, max: 0 },
    G: { min: 0, max: 0 },
});

/* ----------------------------- INITIAL LOAD ------------------------------ */

const initFromModelValue = () => {
    const mv = props.modelValue;
    if (!mv) return;

    scoringRules.value = mv.scoring_rules ? [...mv.scoring_rules] : [];
    maxPlayersPerUser.value = mv.player_limits?.max_per_user ?? 20;

    // Charger les limites par position si elles existent
    if (
        mv.player_limits?.by_position &&
        Object.keys(mv.player_limits.by_position).length > 0
    ) {
        isLimitedPlayers.value = true;
        Object.entries(mv.player_limits.by_position).forEach(
            ([pos, limits]) => {
                if (positionLimits.value[pos]) {
                    positionLimits.value[pos] = { ...limits };
                }
            },
        );
    }
};

initFromModelValue();

/* ------------------------------ EMIT UPDATE ------------------------------ */

const emitUpdate = () => {
    const by_position: Record<string, { min: number; max: number }> = {};

    // Inclure les limites par position seulement si activées
    if (isLimitedPlayers.value) {
        Object.entries(positionLimits.value).forEach(([pos, limits]) => {
            // Inclure uniquement les positions qui ont des limites définies
            if (limits.min > 0 || limits.max > 0) {
                by_position[pos] = {
                    min: Math.max(0, limits.min),
                    max: Math.max(0, limits.max),
                };
            }
        });
    }

    emit('update:modelValue', {
        scoring_rules: scoringRules.value,
        player_limits: {
            max_per_user: Math.max(1, Math.min(50, maxPlayersPerUser.value)),
            by_position:
                Object.keys(by_position).length > 0 ? by_position : undefined,
        },
    });
};

/* ---------------------------- SCORING RULES ------------------------------ */

const availableStats = computed(() =>
    availableStatTypes.filter(
        (stat) => !scoringRules.value.some((rule) => rule.type === stat.value),
    ),
);

const addScoringRule = () => {
    if (!selectedStatType.value) return;

    const statType = availableStatTypes.find(
        (t) => t.value === selectedStatType.value,
    );
    if (!statType) return;

    if (scoringRules.value.some((r) => r.type === selectedStatType.value))
        return;

    scoringRules.value.push({
        type: selectedStatType.value,
        label: statType.label,
        points: 1,
    });

    selectedStatType.value = '';
    emitUpdate();
};

const removeScoringRule = (index: number) => {
    scoringRules.value.splice(index, 1);
    emitUpdate();
};

/* ------------------------------ GLOBAL WATCH ------------------------------ */

watch(
    [scoringRules, maxPlayersPerUser, isLimitedPlayers, positionLimits],
    () => {
        emitUpdate();
    },
    { deep: true },
);
</script>

<template>
    <div class="space-y-6">
        <!-- Scoring -->
        <Card>
            <CardHeader>
                <CardTitle>Règles de pointage</CardTitle>
                <CardDescription>
                    Définissez les points attribués pour chaque statistique
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="flex gap-2">
                    <div class="flex-1">
                        <Select v-model="selectedStatType">
                            <SelectTrigger>
                                <SelectValue
                                    placeholder="Sélectionner une statistique..."
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="stat in availableStats"
                                    :key="stat.value"
                                    :value="stat.value"
                                >
                                    {{ stat.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <Button
                        @click="addScoringRule"
                        :disabled="!selectedStatType"
                    >
                        <Plus class="mr-2 h-4 w-4" />
                        Ajouter
                    </Button>
                </div>

                <div v-if="scoringRules.length > 0" class="space-y-2">
                    <div
                        v-for="(rule, index) in scoringRules"
                        :key="rule.type"
                        class="flex items-center gap-3 rounded-lg border bg-muted/50 p-3"
                    >
                        <div class="flex-1 font-medium">{{ rule.label }}</div>

                        <div class="flex items-center gap-2">
                            <Input
                                v-model.number="rule.points"
                                type="number"
                                min="0"
                                class="w-20 text-center"
                            />
                            <span class="text-sm text-muted-foreground"
                                >pts</span
                            >
                        </div>

                        <Button
                            variant="ghost"
                            size="icon"
                            type="button"
                            @click="removeScoringRule(index)"
                            class="text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950"
                        >
                            <X class="h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <p
                    v-else
                    class="py-4 text-center text-sm text-muted-foreground"
                >
                    Aucune règle de pointage. Ajoutez-en une ci-dessus.
                </p>
            </CardContent>
        </Card>

        <!-- Limits -->
        <Card>
            <CardHeader>
                <div class="grid grid-cols-3">
                    <div class="col-span-2">
                        <CardTitle>Limites de joueurs</CardTitle>
                        <CardDescription>
                            Configurez le nombre maximum de joueurs par
                            utilisateur
                        </CardDescription>
                    </div>
                    <div class="col-span-1 flex justify-end">
                        <Switch v-model="isLimitedPlayers" />
                    </div>
                </div>
            </CardHeader>

            <CardContent v-if="isLimitedPlayers" class="space-y-6">
                <div class="space-y-2">
                    <Label>Joueurs maximum par utilisateur</Label>
                    <Input
                        v-model.number="maxPlayersPerUser"
                        type="number"
                        min="1"
                        max="50"
                        class="max-w-xs"
                    />
                    <p class="text-xs text-muted-foreground">
                        Nombre total de joueurs qu'un utilisateur peut
                        sélectionner
                    </p>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <Label class="text-base font-semibold"
                            >Limites par position</Label
                        >
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="position in availablePositions"
                            :key="position.value"
                            class="flex items-center gap-4 rounded-lg border bg-muted/50 p-3"
                        >
                            <div class="flex flex-1 items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-md bg-primary/10 font-bold text-primary"
                                >
                                    {{ position.shortLabel }}
                                </div>
                                <div class="flex-1">
                                    <Label class="text-sm font-medium">
                                        {{ position.label }}
                                    </Label>
                                </div>
                            </div>

                            <div class="flex items-center gap-3">
                                <div class="flex items-center gap-2">
                                    <Label class="text-xs text-muted-foreground"
                                        >Min</Label
                                    >
                                    <Input
                                        v-model.number="
                                            positionLimits[position.value].min
                                        "
                                        type="number"
                                        min="0"
                                        max="20"
                                        class="w-16 text-center"
                                    />
                                </div>
                                <div class="flex items-center gap-2">
                                    <Label class="text-xs text-muted-foreground"
                                        >Max</Label
                                    >
                                    <Input
                                        v-model.number="
                                            positionLimits[position.value].max
                                        "
                                        type="number"
                                        min="0"
                                        max="20"
                                        class="w-16 text-center"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Laissez à 0 pour aucune limite. Pour forcer un nombre
                        exact, mettez la même valeur dans Min et Max.
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
