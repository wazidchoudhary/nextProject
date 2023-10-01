export const useInjector = () => {
    const injectStyleLink = (url) => {
        const el = document.createElement('link');
        el.rel = 'stylesheet';
        el.href = url;
        document.head.append(el);
    };

    const debounce = (cb) => {
        setTimeout(() => {
            cb();
        }, 3000);
    };

    return {
        injectStyleLink: debounce(injectStyleLink),
    };
};
