<script setup lang="ts">
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/Admin/RuleSettingController';
import DynamicRulesBuilder from '@/components/DynamicRulesBuilder.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

interface ScoringRule {
    type: string;
    label: string;
    points: number;
}

interface PlayerLimits {
    max_per_user: number;
    by_position: Record<string, number>;
}

interface RuleSetting {
    id: number;
    name: string;
    template_type?: string;
    rules?: {
        scoring_rules: ScoringRule[];
        player_limits: PlayerLimits;
    };
    pools_count: number;
}

interface Props {
    ruleSettings: RuleSetting[];
}

defineProps<Props>();

const createModalOpen = ref(false);
const editModalOpen = ref(false);
const editingRuleSetting = ref<RuleSetting | null>(null);

const createForm = useForm({
    name: '',
    rules: {
        scoring_rules: [],
        player_limits: {
            max_per_user: 20,
            by_position: {},
        },
    },
});

const editForm = useForm({
    name: '',
    rules: {
        scoring_rules: [],
        player_limits: {
            max_per_user: 20,
            by_position: {},
        },
    },
});

const handleCreate = () => {
    createForm.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            createModalOpen.value = false;
            createForm.reset();
        },
    });
};

const openEditModal = (ruleSetting: RuleSetting) => {
    editingRuleSetting.value = ruleSetting;
    editForm.name = ruleSetting.name;
    editForm.rules = ruleSetting.rules || {
        scoring_rules: [],
        player_limits: {
            max_per_user: 20,
            by_position: {},
        },
    };
    editModalOpen.value = true;
};

const handleEdit = () => {
    if (!editingRuleSetting.value) return;

    editForm.patch(update.url(editingRuleSetting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editModalOpen.value = false;
            editForm.reset();
            editingRuleSetting.value = null;
        },
    });
};

const handleDelete = (ruleSetting: RuleSetting) => {
    if (ruleSetting.pools_count > 0) {
        alert(
            'Impossible de supprimer ce règlement car il est utilisé par des pools.',
        );
        return;
    }

    if (confirm(`Êtes-vous sûr de vouloir supprimer "${ruleSetting.name}" ?`)) {
        router.delete(destroy.url(ruleSetting.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Règlements - Administration" />

    <AppLayout>
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-semibold">
                        Gestion des règlements
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Créez et gérez les règlements des pools
                    </p>
                </div>
                <Button @click="createModalOpen = true">
                    <Plus class="mr-2 h-4 w-4" />
                    Nouveau règlement
                </Button>
            </div>

            <div class="rounded-md border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Nom</TableHead>
                            <TableHead>Règles de pointage</TableHead>
                            <TableHead class="text-center"
                                >Max joueurs</TableHead
                            >
                            <TableHead class="text-center">Pools</TableHead>
                            <TableHead class="text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="ruleSetting in ruleSettings"
                            :key="ruleSetting.id"
                        >
                            <TableCell class="font-medium">{{
                                ruleSetting.name
                            }}</TableCell>
                            <TableCell>
                                <div class="flex flex-wrap gap-1">
                                    <Badge
                                        v-for="rule in ruleSetting.rules
                                            ?.scoring_rules || []"
                                        :key="rule.type"
                                        variant="outline"
                                        class="text-xs"
                                    >
                                        {{ rule.label }}: {{ rule.points }}pt
                                    </Badge>
                                    <Badge
                                        v-if="
                                            !ruleSetting.rules?.scoring_rules
                                                ?.length
                                        "
                                        variant="secondary"
                                    >
                                        Aucune règle
                                    </Badge>
                                </div>
                            </TableCell>
                            <TableCell class="text-center">
                                {{
                                    ruleSetting.rules?.player_limits
                                        ?.max_per_user || 20
                                }}
                            </TableCell>
                            <TableCell class="text-center">
                                <Badge variant="secondary">{{
                                    ruleSetting.pools_count
                                }}</Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEditModal(ruleSetting)"
                                    >
                                        <Pencil class="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="handleDelete(ruleSetting)"
                                        :disabled="ruleSetting.pools_count > 0"
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

        <!-- Create Modal -->
        <Dialog v-model:open="createModalOpen">
            <DialogContent
                class="max-h-[90vh] overflow-y-auto sm:max-w-[700px]"
            >
                <DialogHeader>
                    <DialogTitle>Créer un nouveau règlement</DialogTitle>
                    <DialogDescription>
                        Configurez les règles de pointage et les limites de
                        joueurs
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleCreate" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="create-name">Nom du règlement</Label>
                        <Input
                            id="create-name"
                            v-model="createForm.name"
                            placeholder="Ex: Règles personnalisées..."
                            :disabled="createForm.processing"
                        />
                        <InputError :message="createForm.errors.name" />
                    </div>

                    <DynamicRulesBuilder
                        v-model="createForm.rules"
                        :show-templates="false"
                    />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="createModalOpen = false"
                            :disabled="createForm.processing"
                        >
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="createForm.processing">
                            {{
                                createForm.processing ? 'Création...' : 'Créer'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Modal -->
        <Dialog v-model:open="editModalOpen">
            <DialogContent
                class="max-h-[90vh] overflow-y-auto sm:max-w-[700px]"
            >
                <DialogHeader>
                    <DialogTitle>Modifier le règlement</DialogTitle>
                    <DialogDescription>
                        Modifiez les règles de pointage et les limites de
                        joueurs
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="handleEdit" class="space-y-4">
                    <div class="space-y-2">
                        <Label for="edit-name">Nom du règlement</Label>
                        <Input
                            id="edit-name"
                            v-model="editForm.name"
                            :disabled="editForm.processing"
                        />
                        <InputError :message="editForm.errors.name" />
                    </div>

                    <DynamicRulesBuilder
                        v-model="editForm.rules"
                        :show-templates="false"
                    />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="editModalOpen = false"
                            :disabled="editForm.processing"
                        >
                            Annuler
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            {{
                                editForm.processing
                                    ? 'Sauvegarde...'
                                    : 'Sauvegarder'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
