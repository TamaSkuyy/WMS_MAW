import { router } from "@inertiajs/react";

const useGoBack = () => {
  const goBack = () => {
    if (window.history.length > 1) {
      window.history.back(); // Go back to the previous page
    } else {
      router.visit("/"); // Redirect to home if no history exists
    }
  };

  return goBack;
};

export default useGoBack;
