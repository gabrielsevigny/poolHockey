<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store as loginStore } from '@/routes/login';
import { request as passwordRequest } from '@/routes/password';
import { store as registerStore } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = withDefaults(
    defineProps<{
        canRegister: boolean;
        canResetPassword: boolean;
        status?: string;
    }>(),
    {
        canRegister: true,
        canResetPassword: true,
    },
);

const isLogin = ref(true);
</script>

<template>
    <Head title="Pool De Hockey" />

    <div class="flex min-h-screen flex-col-reverse justify-end gap-x-16 lg:grid lg:grid-cols-2 lg:justify-normal lg:p-16">
        <!-- Colonne gauche: Formulaire -->
        <div class="flex min-h-[60vh] items-center justify-center py-16 lg:min-h-0">
            <div class="block w-full max-w-lg">
                <!-- En-tête -->
                <div class="mb-8 text-center">
                    <h1 class="text-3xl font-bold tracking-tight">
                        {{ isLogin ? 'Bienvenue' : 'Créer un compte' }}
                    </h1>
                    <p class="mt-2 text-muted-foreground">
                        {{ isLogin ? 'Connectez-vous pour gérer votre pool de hockey' : 'Rejoignez-nous pour participer au pool' }}
                    </p>
                </div>

                <!-- Message de statut -->
                <div v-if="status" class="mb-4 rounded-md bg-green-50 p-3 text-center text-sm font-medium text-green-600 dark:bg-green-900/20 dark:text-green-400">
                    {{ status }}
                </div>

                <!-- Toggle Login/Register -->
                <div v-if="canRegister" class="mb-6 flex gap-2 rounded-lg bg-muted p-1">
                    <button type="button" @click="isLogin = true" :class="['flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors', isLogin ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground']">
                        Connexion
                    </button>
                    <button type="button" @click="isLogin = false" :class="['flex-1 rounded-md px-4 py-2 text-sm font-medium transition-colors', !isLogin ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground']">
                        Inscription
                    </button>
                </div>

                <!-- Formulaire de connexion -->
                <Form v-if="isLogin" v-bind="loginStore.form()" :reset-on-success="['password']" v-slot="{ errors, processing }" class="flex flex-col gap-6">
                    <div class="grid gap-6">
                        <div class="grid gap-2">
                            <Label for="email">Adresse courriel</Label>
                            <Input id="email" type="email" name="email" required autofocus autocomplete="email" placeholder="votre@email.com" />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <div class="flex items-center justify-between">
                                <Label for="password">Mot de passe</Label>
                            </div>
                            <Input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••" />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="flex items-center justify-between">
                            <Label for="remember" class="flex items-center">
                                <Checkbox id="remember" name="remember" />
                                <span>Se souvenir de moi</span>
                            </Label>

                            <TextLink v-if="canResetPassword" :href="passwordRequest()" class="text-sm"> Mot de passe oublié? </TextLink>
                        </div>

                        <Button type="submit" class="w-full" :disabled="processing">
                            <Spinner v-if="processing" />
                            Se connecter
                        </Button>
                    </div>
                </Form>

                <!-- Formulaire d'inscription -->
                <Form v-else v-bind="registerStore.form()" :reset-on-success="['password', 'password_confirmation']" v-slot="{ errors, processing }" class="flex flex-col gap-6">
                    <div class="grid gap-6">
                        <div class="grid gap-2">
                            <Label for="name">Nom complet</Label>
                            <Input id="name" type="text" name="name" required autofocus autocomplete="name" placeholder="Votre nom" />
                            <InputError :message="errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="email-register">Adresse courriel</Label>
                            <Input id="email-register" type="email" name="email" required autocomplete="email" placeholder="votre@email.com" />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password-register">Mot de passe</Label>
                            <Input id="password-register" type="password" name="password" required autocomplete="new-password" placeholder="••••••••" />
                            <InputError :message="errors.password" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="password_confirmation">Confirmer le mot de passe</Label>
                            <Input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <Button type="submit" class="w-full" :disabled="processing">
                            <Spinner v-if="processing" />
                            Créer mon compte
                        </Button>
                    </div>
                </Form>
            </div>
        </div>

        <!-- Colonne droite: Image -->
        <div class="relative flex place-items-end pt-[120px]">
            <img src="/mcdavid-front.jpg" alt="Connor McDavid" class="absolute h-full w-full object-cover object-top lg:rounded-2xl" />
            <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent lg:rounded-2xl"></div>
            <div class="relative z-10 m-6 w-full rounded-xl bg-white/20 p-6 text-white shadow-2xl backdrop-blur-lg backdrop-saturate-150 md:pt-6">
                <h2 class="text-4xl font-bold drop-shadow-lg">Pool de Hockey</h2>
                <p class="mt-2 text-lg text-white/90 drop-shadow-md">Gérez votre pool et suivez vos performances</p>
            </div>
        </div>
    </div>
</template>
