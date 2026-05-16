// react plugin for creating vector maps
// NOTE: @react-jvectormap is currently disabled due to Vite compatibility issues with Webpack CSS loaders.
// import { VectorMap } from "@react-jvectormap/core";
// import { worldMill } from "@react-jvectormap/world";

interface CountryMapProps {
  mapColor?: string;
}

const CountryMap: React.FC<CountryMapProps> = ({ mapColor }) => {
  return (
    <div className="flex items-center justify-center w-full h-[250px] bg-gray-50 dark:bg-gray-800 rounded-lg border border-dashed border-gray-300 dark:border-gray-700">
      <p className="text-sm text-gray-500 dark:text-gray-400">
        Map visualization is temporarily disabled.
      </p>
    </div>
  );
};

export default CountryMap;
