import { useState } from "react";

export const FilterSection = ({ data }) => {
  const { products, search, setSearch, setSorting } =
    data;

  return (
    <div className="main-case">
      {/* <div> */}
        <input
          value={search}
          type="text"
          placeholder="Search by Name, Category, Sub-Category"
          onChange={(event) => setSearch(event.target.value)}
        />
      {/* </div> */}

      <div>
        <button
          className="btn"
          onClick={() => setSorting('High-To-Low')}
        >
          Hight To Low
        </button>

        <button
          className="btn"
          onClick={() => setSorting('Low-To-High')}
        >
          Low To High
        </button>
      </div>
    </div>
  );
};
