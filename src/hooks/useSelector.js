import store from '@/lib/store/store';
import {useState, useEffect} from 'react';

const useSelector = (key) => {
    const [reduxStore, setReduxStore] = useState({});

    useEffect(() => {
        store.subscribe((store) => {
            setReduxStore(store || {})
        })
    }, []);

    if(key === '*') return reduxStore || {};

    return reduxStore[key];
}

export default useSelector;