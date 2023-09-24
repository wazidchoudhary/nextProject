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

    const sortByAscendingOrder = sorting === 'Low-To-High' ? filterBySearch.sort((a, b) => Number(a.productPrice) - Number(b.productPrice)) : filterBySearch;

    const sortByDescendingOrder = sorting === 'High-To-Low' ? sortByAscendingOrder.sort((a, b) => Number(b.productPrice) - Number(a.productPrice)) : sortByAscendingOrder;

    return sortByDescendingOrder;
}
