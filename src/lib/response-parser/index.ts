export class ResponseParser {
    static parse<T>(obj: any) {
        const res = []
        obj.forEach((snap)=>{
            res.push(snap.val())
        })
        return res;
    }
}