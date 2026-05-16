import { useEffect } from "react";
import { usePage } from "@inertiajs/react";

export function ScrollToTop() {
  const { url: pathname } = usePage();

  useEffect(() => {
    window.scrollTo({
      top: 0,
      left: 0,
      behavior: "smooth",
    });
  }, [pathname]);

  return null;
}
