export class ResponseParser {
    static parse<T>(obj: any) {
        const res: T[] = [];
        Object.keys(obj).forEach(key => {
            res.push({id: key, ...obj[key]})
        });
        return res;
    }
}