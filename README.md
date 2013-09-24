WZP tools
=========

Tools for Unpacking / Packing YFAPP.WZP firmware file for GPS Navigation based on YF launcher.

You can use [compiled binaries for Windows](http://yadi.sk/d/UR_Tc_Mf9qFpL)

WZP_UNPACK
----------
Usage: wzp_unpack [yfapp.wzp] [out_dir]

All params are optional.

First - input YFAPP.WZP file.

Second - output directory. By default it is "Unpacked".

WZP_PACK
--------
Usage: wzp_pack [in_dir] [out_yfapp.wzp] [method] [hex_flag]

Example: wzp_pack Input out_yfapp.wzp 8 0xCCCCCCCC

All params are optional.

First param "in_dir" - is input directory. Default value is "Input". There must be root directory named "YFAPP" with files in it.

Second param "out_yfapp.wzp" - is output filename. By default it is "out_yfapp.wzp".

Thrid - method. Default is 8 (of zlib compression level). Useful values are 0 (uncompressed) - 9 (maximum).

Default method for YFAPP.WZP in most cases is 6, but it is named 8 in firmware files.

I tested firmware with 8, compressed by zlib as 8 and it works normal.

Fourth - hex_flag is chunk attribute flag, seems to be unused. Default values selected from other firmwares.

0xCCCCCCCC for YFAPP, for YFAP20 is 0x0154F4E4, and for YFAP30 is 0x0012D830.
