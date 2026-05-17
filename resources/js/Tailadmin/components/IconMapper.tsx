import React from 'react';
import {
  BoxCubeIcon,
  CalenderIcon,
  ChevronDownIcon,
  GridIcon,
  HorizontaLDots,
  ListIcon,
  PageIcon,
  PieChartIcon,
  PlugInIcon,
  TableIcon,
  UserCircleIcon,
} from "../icons";

const iconsMap: Record<string, React.ReactNode> = {
  BoxCubeIcon: <BoxCubeIcon />,
  CalenderIcon: <CalenderIcon />,
  ChevronDownIcon: <ChevronDownIcon />,
  GridIcon: <GridIcon />,
  HorizontaLDots: <HorizontaLDots />,
  ListIcon: <ListIcon />,
  PageIcon: <PageIcon />,
  PieChartIcon: <PieChartIcon />,
  PlugInIcon: <PlugInIcon />,
  TableIcon: <TableIcon />,
  UserCircleIcon: <UserCircleIcon />,
};

interface IconMapperProps {
  name?: string;
  className?: string;
}

export default function IconMapper({ name, className }: IconMapperProps) {
  if (!name || !iconsMap[name]) {
    // Return a default icon or null if not found
    return <BoxCubeIcon className={className} />;
  }

  // Clone the element to pass the className
  return React.cloneElement(iconsMap[name] as React.ReactElement<{ className?: string }>, { className });
}
