import { FC, ReactNode } from "react";

interface LabelProps {
  htmlFor?: string;
  children: ReactNode;
  className?: string;
}

const Label: FC<LabelProps> = ({ htmlFor, children, className = "" }) => {
  return (
    <label
      htmlFor={htmlFor}
      className={`block text-[13px] font-medium text-[#1A1D23] mb-1.5 ${className}`}
    >
      {children}
    </label>
  );
};

export default Label;
