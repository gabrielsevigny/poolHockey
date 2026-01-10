<script setup lang="ts">
import { store } from '@/actions/App/Http/Controllers/PoolController';
import DynamicRulesBuilder from '@/components/DynamicRulesBuilder.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useForm } from '@inertiajs/vue3';
import { Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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
    open: boolean;
    ruleSettings: RuleSetting[];
    users: User[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const searchQuery = ref('');
const userPopoverOpen = ref(false);
const ruleMode = ref<'existing' | 'custom'>('existing');
const customRules = ref<any>(null);

const form = useForm({
    name: '',
    start_date: '',
    end_date: '',
    rule_setting_id: '' as string,
    user_ids: [] as number[],
    custom_rules: null as any,
});

// Watch for rule mode changes
watch(ruleMode, (newMode) => {
    if (newMode === 'existing') {
        form.custom_rules = null;
    } else {
        form.rule_setting_id = '';
    }
});

const filteredUsers = computed(() => {
    if (!searchQuery.value) {
        return props.users;
    }
    const query = searchQuery.value.toLowerCase();
    return props.users.filter(
        (user) =>
            user.name.toLowerCase().includes(query) ||
            user.email.toLowerCase().includes(query),
    );
});

const selectedUsers = computed(() => {
    return props.users.filter((user) => form.user_ids.includes(user.id));
});

const toggleUser = (userId: number) => {
    const index = form.user_ids.indexOf(userId);
    if (index > -1) {
        form.user_ids.splice(index, 1);
    } else {
        form.user_ids.push(userId);
    }
};

const removeUser = (userId: number) => {
    const index = form.user_ids.indexOf(userId);
    if (index > -1) {
        form.user_ids.splice(index, 1);
    }
};

const handleSubmit = () => {
    form.post(store.url(), {
        onSuccess: () => {
            emit('update:open', false);
            form.reset();
        },
    });
};

const handleOpenChange = (value: boolean) => {
    if (!value) {
        form.reset();
        form.clearErrors();
        ruleMode.value = 'existing';
        customRules.value = null;
    }
    emit('update:open', value);
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-[700px]">
            <DialogHeader>
                <DialogTitle>Créer un nouveau pool</DialogTitle>
                <DialogDescription>
                    Remplissez les informations pour créer un nouveau pool de
                    hockey.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="name">Nom du pool</Label>
                    <Input
                        id="name"
                        v-model="form.name"
                        placeholder="Pool de la semaine"
                        :disabled="form.processing"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="aligns-start grid grid-cols-2 space-y-3 gap-x-4">
                    <div>
                        <Label for="start_date" class="mb-2">
                            Date du repêchage (début)
                        </Label>

                        <Input
                            id="start_date"
                            v-model="form.start_date"
                            type="date"
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.start_date" />
                    </div>

                    <div>
                        <Label for="end_date">Date de fin du pool</Label>
                        <Input
                            id="end_date"
                            v-model="form.end_date"
                            type="date"
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.end_date" />
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Le repêchage dure 1 journée. Le pool devient actif le
                        lendemain.
                    </p>
                </div>

                <div class="space-y-2">
                    <Label>Règlements</Label>
                    <Tabs v-model="ruleMode" class="w-full">
                        <TabsList class="grid w-full grid-cols-2">
                            <TabsTrigger value="existing">
                                Règles existantes
                            </TabsTrigger>
                            <TabsTrigger value="custom">
                                Règles personnalisées
                            </TabsTrigger>
                        </TabsList>
                        <TabsContent value="existing" class="space-y-2">
                            <Select
                                v-model="form.rule_setting_id"
                                :disabled="form.processing"
                            >
                                <SelectTrigger>
                                    <SelectValue
                                        placeholder="Sélectionner un règlement"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="setting in ruleSettings"
                                        :key="setting.id"
                                        :value="String(setting.id)"
                                    >
                                        {{ setting.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.rule_setting_id"
                            />
                        </TabsContent>
                        <TabsContent value="custom" class="space-y-2">
                            <DynamicRulesBuilder v-model="form.custom_rules" />
                            <InputError :message="form.errors.custom_rules" />
                        </TabsContent>
                    </Tabs>
                </div>

                <div class="space-y-2">
                    <Label>Participants</Label>
                    <Popover v-model:open="userPopoverOpen">
                        <PopoverTrigger as-child>
                            <Button
                                variant="outline"
                                class="w-full justify-start"
                                :disabled="form.processing"
                            >
                                <Search class="mr-2 h-4 w-4" />
                                {{
                                    selectedUsers.length > 0
                                        ? `${selectedUsers.length} utilisateur(s) sélectionné(s)`
                                        : 'Sélectionner des utilisateurs'
                                }}
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-[400px] p-0" align="start">
                            <div class="border-b p-2">
                                <Input
                                    v-model="searchQuery"
                                    placeholder="Rechercher un utilisateur..."
                                    class="h-9"
                                />
                            </div>
                            <div class="max-h-[300px] overflow-y-auto p-2">
                                <div
                                    v-if="filteredUsers.length === 0"
                                    class="py-6 text-center text-sm text-muted-foreground"
                                >
                                    Aucun utilisateur trouvé
                                </div>
                                <div
                                    v-for="user in filteredUsers"
                                    :key="user.id"
                                    class="flex cursor-pointer items-center space-x-2 rounded-md px-2 py-1.5 hover:bg-accent"
                                    @click="toggleUser(user.id)"
                                >
                                    <Checkbox
                                        :checked="
                                            form.user_ids.includes(user.id)
                                        "
                                        @click.stop="toggleUser(user.id)"
                                    />
                                    <div class="flex-1">
                                        <div class="text-sm font-medium">
                                            {{ user.name }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ user.email }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </PopoverContent>
                    </Popover>

                    <div
                        v-if="selectedUsers.length > 0"
                        class="mt-2 flex flex-wrap gap-2"
                    >
                        <Badge
                            v-for="user in selectedUsers"
                            :key="user.id"
                            variant="secondary"
                            class="gap-1"
                        >
                            {{ user.name }}
                            <button
                                type="button"
                                @click="removeUser(user.id)"
                                class="ml-1 rounded-full hover:bg-muted"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </Badge>
                    </div>
                    <InputError :message="form.errors.user_ids" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="handleOpenChange(false)"
                        :disabled="form.processing"
                    >
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Création...' : 'Créer le pool' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
