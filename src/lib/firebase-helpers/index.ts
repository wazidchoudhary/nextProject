import {app, database} from './firebase-config';
import { getDatabase, get, child, ref, push } from "firebase/database";
import { Product } from '@/types/types';
import { ResponseParser } from '../response-parser';

const dbRef = ref(database);
const sync = (entity: string) => get(child(dbRef, `${entity}/`));

export class FirebaseHelper {
    static async syncAllProducts(): Promise<Product[]> {
        const snapshot = await sync('product');
         let projects = ResponseParser.parse<Product>(snapshot);
        
        return projects
    }
}