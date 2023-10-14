import { useState } from 'react';

export const FilterSection = ({ data }) => {
    const { products, search, setSearch, setSorting, setLayout, layout } = data;
    console.log(layout);
    const sorting = (e) => {
        setSorting(e.target.value);
    };
    const classActive = 'active';
    return (
        <div className="shop-toolbar mb--30">
            <div className="row align-items-center">
                <div className="col-lg-6 col-sm-6">
                    <div className="product-view-mode w-25 d-none d-md-inline">
                        <a className={`sorting-btn ${layout === 'list' ? classActive : ''}`} data-bs-target="list" onClick={() => setLayout('list')}>
                            <i className="fas fa-list" />
                        </a>
                        <a className={`sorting-btn ${layout === 'grid' ? classActive : ''}`} data-bs-target="grid" onClick={() => setLayout('grid')}>
                            <i className="fas fa-th" />
                        </a>
                    </div>
                    <input
                        className="form-control w-100 d-inline search-box"
                        value={search}
                        type="text"
                        placeholder="Search by Name, Category, Sub-Category"
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </div>

                <div className="col-lg-6 col-sm-6 mt-sm-0 mt-3">
                        <p className='d-none d-md-inline'>Sort By:</p>
                        <select id="input-sort" onChange={sorting} className="form-control w-100" >
                            <option value="" selected="selected">
                                Default Sorting
                            </option>
                            <option value="Az">Sort By:Name (A - Z)</option>
                            <option value="Za">Sort By:Name (Z - A)</option>
                            <option value="lowToHigh">Sort By:Price (Low &gt; High)</option>
                            <option value="highToLow">Sort By:Price (High &gt; Low)</option>
                        </select>
                    
                </div>
            </div>
        </div>
    );
};
