<template>
    <article
        class="group flex flex-col overflow-hidden rounded-2xl border border-line bg-surface transition-shadow duration-150 ease-qayema hover:shadow-lift"
    >
        <div
            v-if="$slots.media"
            data-card-media
            class="relative aspect-[4/3] w-full overflow-hidden bg-hover-wash"
        >
            <slot name="media" />
        </div>

        <div class="flex flex-1 flex-col gap-1 p-4">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 data-card-title class="truncate font-medium text-fg"><slot name="title" /></h3>
                    <p v-if="$slots.subtitle" data-card-subtitle class="truncate text-sm text-muted">
                        <slot name="subtitle" />
                    </p>
                </div>
                <div v-if="$slots.badge" class="shrink-0"><slot name="badge" /></div>
            </div>
            <div v-if="$slots.meta" data-card-meta class="mt-1 text-sm"><slot name="meta" /></div>
            <slot />
        </div>

        <div
            v-if="$slots.actions"
            data-card-actions
            class="flex items-center justify-end gap-1 border-t border-line-2 px-3 py-2"
        >
            <slot name="actions" />
        </div>
    </article>
</template>
