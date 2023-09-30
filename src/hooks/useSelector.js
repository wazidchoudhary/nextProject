import store from '@/lib/store/store';
import { useState, useEffect } from 'react';

const useSelector = (func) => {
    const [reduxStore, setReduxStore] = useState({});

    useEffect(() => {
        const { unsubscribe } = store.subscribe((store) => {
            setReduxStore(store || {});
        });

        return () => {
            unsubscribe();
        };
    }, []);

    return func(reduxStore);
};

export default useSelector;
