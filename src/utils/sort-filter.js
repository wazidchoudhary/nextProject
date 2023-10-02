import { priceHelper } from "@/lib/price-helper";
export function Filters(search, products, sorting) {
    const searchLowerCase = search.toLowerCase();
 
    const filterBySearch =
        search.length > 0
            ? products.filter(
                  (item) =>
                      item.productName.toLowerCase().includes(searchLowerCase) ||
                      item.productCategory.toLowerCase().includes(searchLowerCase) ||
                      item.productSubCategory.toLowerCase().includes(searchLowerCase)
              )
            : products;

    const sortByAscendingOrder = sorting === 'lowToHigh' ? filterBySearch.sort((a, b) =>{
            const priceA =  typeof a.productPrice !== 'string' ? priceHelper.lowestHighestPrice(a.productPrice).lowest : a.productPrice
            const priceB =  typeof b.productPrice !== 'string' ? priceHelper.lowestHighestPrice(b.productPrice).lowest : b.productPrice
        
            return Number(priceA) - Number(priceB)
        
        }) : filterBySearch;

    const sortByDescendingOrder = sorting === 'highToLow' ? sortByAscendingOrder.sort((a, b) => {
            const priceA =  typeof a.productPrice !== 'string' ? priceHelper.lowestHighestPrice(a.productPrice).lowest : a.productPrice
            const priceB =  typeof b.productPrice !== 'string' ? priceHelper.lowestHighestPrice(b.productPrice).lowest : b.productPrice
            return Number(priceB) - Number(priceA) 
        }) : sortByAscendingOrder;
    
    const sortByAz = sorting === 'Az' ? sortByDescendingOrder.sort((a,b) =>{
        if(a.productName.toLowerCase() < b.productName.toLowerCase()){
            return -1
        }
        if(a.productName.toLowerCase() > b.productName.toLowerCase()){
            return 1
        }
        return 0
        
    }) : sortByDescendingOrder

    const sortByZa = sorting === 'Za' ? sortByAz.sort((a,b)=>{
        if(a.productName.toLowerCase() < b.productName.toLowerCase()){
            return 1
        }
        if(a.productName.toLowerCase() > b.productName.toLowerCase()){
            return -1
        }
        return 0
    }) : sortByAz
       
    return sortByZa;
}
