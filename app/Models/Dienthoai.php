<?php

namespace App;
use Illuminate\Database\Eloquent\Model;

class Dienthoai extends Model {
	
    protected $table = 'dienthoai';
	
    public $timestamps = false;
	
	protected $fillable = ['dt_ten', 'dt_hdn', 'dt_sluong', 'dt_thuonghieu', 'dt_gia', 'dt_hinh', 'dt_loai', 'dt_kco', 'dt_pgiai', 'dt_pin', 'dt_hdh', 'dt_ram', 'dt_bnho', 'dt_cam'];

    public static  function getList()
    {

        return \DB::table('dienthoai')->join('hoadonnhap','dt_hdn','=','hdn_maso')->join('nguoidung','hdn_nguoidung','=','nd_maso')->paginate(1,['*'],'page_dt');
    }
    public static  function getthuonghieu($th_maso)
    {
        return \DB::table('dienthoai')->join('hoadonnhap','dt_hdn','=','hdn_maso')->join('nguoidung','hdn_nguoidung','=','nd_maso')->where('dt_thuonghieu','=',$th_maso)->paginate(1,['*'],'page_dt');
    }
    public static  function getnguoiban($nd_maso)
    {
        return \DB::table('dienthoai')->join('hoadonnhap','dt_hdn','=','hdn_maso')->join('nguoidung','hdn_nguoidung','=','nd_maso')->where('nd_maso','=',$nd_maso)->paginate(1,['*'],'page_dt');
    }
    public static function chitiet($dt_maso)
    {
        return \DB::table('dienthoai')->join('hoadonnhap','dt_hdn','=','hdn_maso')->join('thuonghieu','dt_thuonghieu','=','th_maso')->where('dt_maso','=',$dt_maso)->first();
    }
    public static function timkiem($dt_ten, $dt_thuonghieu,$dt_gia_tu,$dt_gia_den)
    {
        return \DB::table('dienthoai')->join('hoadonnhap','dt_hdn','=','hdn_maso')->join('nguoidung','hdn_nguoidung','=','nd_maso')->where([['dt_ten','like','%'.$dt_ten.'%'],['dt_thuonghieu','=',$dt_thuonghieu],['dt_gia','>=',$dt_gia_tu],['dt_gia','<=',$dt_gia_den]])->paginate(1,['*'],'page_dt');
    }
}
