<script setup lang="ts">
import { store } from '@/actions/App/Http/Controllers/PoolController';
import DynamicRulesBuilder from '@/components/DynamicRulesBuilder.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

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

interface Props {
    open: boolean;
    ruleSettings: RuleSetting[];
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const ruleMode = ref<'existing' | 'custom'>('existing');
const customRules = ref<any>(null);

const form = useForm({
    name: '',
    start_date: '',
    end_date: '',
    rule_setting_id: '' as string,
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
                <DialogDescription> Remplissez les informations pour créer un nouveau pool de hockey. </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div class="space-y-2">
                    <Label for="name">Nom du pool</Label>
                    <Input id="name" v-model="form.name" placeholder="Pool de la semaine" :disabled="form.processing" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="aligns-start grid grid-cols-2 space-y-3 gap-x-4">
                    <div>
                        <Label for="start_date" class="mb-2"> Date du repêchage (début) </Label>

                        <Input id="start_date" v-model="form.start_date" type="date" :disabled="form.processing" />
                        <InputError :message="form.errors.start_date" />
                    </div>

                    <div>
                        <Label for="end_date">Date de fin du pool</Label>
                        <Input id="end_date" v-model="form.end_date" type="date" :disabled="form.processing" />
                        <InputError :message="form.errors.end_date" />
                    </div>

                    <p class="text-xs text-muted-foreground">Le repêchage dure 1 journée. Le pool devient actif le lendemain.</p>
                </div>

                <div class="space-y-2">
                    <Label>Règlements</Label>
                    <Tabs v-model="ruleMode" class="w-full">
                        <TabsList class="grid w-full grid-cols-2">
                            <TabsTrigger value="existing"> Règles existantes </TabsTrigger>
                            <TabsTrigger value="custom"> Règles personnalisées </TabsTrigger>
                        </TabsList>
                        <TabsContent value="existing" class="space-y-2">
                            <Select v-model="form.rule_setting_id" :disabled="form.processing">
                                <SelectTrigger>
                                    <SelectValue placeholder="Sélectionner un règlement" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="setting in ruleSettings" :key="setting.id" :value="String(setting.id)">
                                        {{ setting.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.rule_setting_id" />
                        </TabsContent>
                        <TabsContent value="custom" class="space-y-2">
                            <DynamicRulesBuilder v-model="form.custom_rules" />
                            <InputError :message="form.errors.custom_rules" />
                        </TabsContent>
                    </Tabs>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="handleOpenChange(false)" :disabled="form.processing"> Annuler </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Création...' : 'Créer le pool' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
