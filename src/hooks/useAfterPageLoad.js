import { useEffect, useState } from "react";

const useAfterPageLoad = (initialVal, defaultValue, delay=3000) => {
    const [val, setVal] = useState(defaultValue);

    useEffect(() => {
      const to = setTimeout(setVal, delay, initialVal);
      return () => clearTimeout(to)
    });

    return [
        val,
        setVal
    ]

}

export default useAfterPageLoad;