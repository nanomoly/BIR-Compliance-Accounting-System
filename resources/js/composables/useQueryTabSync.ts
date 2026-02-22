import { watch, type Ref } from 'vue';

export function useQueryTabSync(
    activeTab: Ref<string>,
    allowedTabs: readonly string[],
    queryKey = 'tab',
): { syncFromQuery: () => void } {
    const syncFromQuery = (): void => {
        const query = new URLSearchParams(window.location.search);
        const tab = query.get(queryKey);

        if (tab && allowedTabs.includes(tab)) {
            activeTab.value = tab;
        }
    };

    watch(activeTab, (tab) => {
        const url = new URL(window.location.href);
        url.searchParams.set(queryKey, tab);
        window.history.replaceState({}, '', `${url.pathname}?${url.searchParams.toString()}`);
    });

    return { syncFromQuery };
}
