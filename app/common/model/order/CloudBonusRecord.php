<?php

namespace app\common\model\order;

use app\shop\service\order\ExportService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\Model;

/**
 * 分红明细表
 */
class CloudBonusRecord extends Model
{
    protected $pk = 'bonus_record_id';
    protected $name = 'cloud_bonus_record';

    /**
     * 订单导出
     */
    public function exportList($query)
    {
        // 获取订单列表
        $list = $this->getListAll($query);
        // 导出excel文件
        return $this->excelList($list);
    }

    /**
     * 订单列表(全部)
     */
    private function getListAll($query = [])
    {
        $model = $this;
        // 检索查询条件
        if (isset($query['type']) && $query['type'] != '') {
            $model = $model->where('type', $query['type']);
        }
        // 获取数据列表
        return $model->order(['create_time' => 'desc'])->select();
    }

    /**
     * 订单导出
     */
    private function excelList($list)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        //列宽
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(15);
        $sheet->getColumnDimension('J')->setWidth(15);
        $sheet->getColumnDimension('K')->setWidth(15);

        //设置工作表标题名称
        $sheet->setTitle('分红明细');

        $sheet->setCellValue('A1', '期数');
        $sheet->setCellValue('B1', '上期余留');
        $sheet->setCellValue('C1', '本期池子');
        $sheet->setCellValue('D1', '本期总池');
        $sheet->setCellValue('E1', '分红数额');
        $sheet->setCellValue('F1', '分红人数');
        $sheet->setCellValue('G1', '分红数额比例（%）');
        $sheet->setCellValue('H1', '本期总销售额');
        $sheet->setCellValue('I1', '开始日期');
        $sheet->setCellValue('J1', '结束日期');
        $sheet->setCellValue('K1', '分红类型');

        //填充数据
        $index = 0;
        $i = 0;
        foreach ($list as $order) {
            $kk = $index + $i;
            $sheet->setCellValueExplicit('A' . ($kk + 2), $order['date_record'], 's');
            $sheet->setCellValue('B' . ($kk + 2), $order['last_bonus']);
            $sheet->setCellValue('C' . ($kk + 2), $order['now_bonus']);
            $sheet->setCellValue('D' . ($kk + 2), $order['all_bonus']);
            $sheet->setCellValue('E' . ($kk + 2), $order['write_bonus']);
            $sheet->setCellValue('F' . ($kk + 2), $order['write_bonus_user']);
            $sheet->setCellValue('G' . ($kk + 2), $order['write_bonus_ratio']);
            $sheet->setCellValue('H' . ($kk + 2), $order['write_bonus_ratio']);
            $sheet->setCellValue('I' . ($kk + 2), $order['start_date']);
            $sheet->setCellValue('J' . ($kk + 2), $order['end_date']);
            $sheet->setCellValue('K' . ($kk + 2), $order['type']);
            $i++;
            $i = $i - 1;
            $index++;
        }

        //保存文件
        $writer = new Xlsx($spreadsheet);
        $filename = iconv("UTF-8", "GB2312//IGNORE", '分红') . '-' . date('YmdHis') . '.xlsx';


        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit();
    }

}