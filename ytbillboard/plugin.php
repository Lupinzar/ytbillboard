<?php namespace ytbillboard;

/*
	This is by no means a complete file spec for the
	Yoot Tower plugins. I was not able to decipher the
	function of every single byte.
	
	For English Windows version, have not tested
	Mac or The Tower II.
	
	Coded by Steven Martin (Lupinzar)
*/


class plugin {
	//for pack()
	const LONG = 'V';
	const SHORT = 'v';
	const BYTE = 'C';
	const NULLBYTE = 'x';
	
	const MAGICNUM = 'SP'; //first two bytes of file
	
	//There are several four character strings in the files
	//They are backwards (pretty sure).
	const ATTR = 'RTTA'; 
	const DESC = 'CSED';
	const ADDF = 'FDDA';
	const ADPL = 'lPdA';
	const BOPI = 'IPoB';
	
	const HEADERSIZE = 0x46;  //plugin header size
	
	//limits
	const IDLENGTH = 3;
	/* 
		Don't make NAMES too long or game crashes.
		The status bar in the game fits about 47 characters, but you can make
		the company name up to 60 before crashing.
	*/
	const PLUGINNAMELIMIT = 100;
	const COMPANYNAMELIMIT = 60;
	/*
		Stock billboards seem to use 5 days for contract length.
		It appears you can use a full 32-bit unsigned integer for this, but I 
		don't recommend for playability. Game may also do some sort of boundry
		check or clamping.
	*/
	const CONTRACTMIN = 1;
	const CONTRACTMAX = 0xFFFFFFFF;
	/*
		Stock billboards seem to use 500 for income, which is $50,000 in game.
		I was not able to crash the game with too high or low of a value, 
		but larger numbers produce strange results. And the player's currency
		can overflow, so be careful.
	*/
	const INCOMEMIN = -2147483648;
	const INCOMEMAX = 2147483647;
	
	//BITMAP STUFF
	const BITMAPDIBSIZE = 0x28;  //size of Bitmap DIB header
	const WIDTH = 160;	//Other sizes? I doubt it would work.
	const HEIGHT = 60;
	
	//offsets and lengths for sections
	protected $attrOffset;
	protected $attrLength;
	protected $descOffset;
	protected $descLength;
	protected $addfOffset;
	protected $addfLength;
	protected $bitmapOffset;
	protected $bitmapLength;
	
	//plugin info
	/*
		id is important, it's made up of 4 characters that uniquely identify
		the plugin. I'm only going to allow you to use 3 characters though
		because all of the stock billboards start (after you reverse them)
		with "B". I believe this keeps conflicts with other plugins to a 
		minimum.
		
		Case sensitivty? Force upper for now. All stock billboards are upper.
	*/
	protected $id;
	protected $pluginName; //appears in plugin info and billboard dialog
	protected $companyName; //appears in status bar
	protected $income; //will be multiplied by 100 by the game e.g. 500 = $50,000
	protected $contractLength; //how long billboard is up, in game days
	
	protected $outputPath; //output file path
	protected $imagePath; //path to image file
	protected $imageCopyPath = false; //will save a copy of the 256 color image as PNG
	protected $dataBuffer = '';
	
	protected $img; //GD image for placeholding
	
	public function setOutputPath($path) {
		$this->outputPath = $path;
	}
	
	public function setImagePath($path) {
		$this->imagePath = $path;
	}
	
	public function setImageCopyPath($path) {
		$this->imageCopyPath = $path;
	}
	
	public function setId($str) {
		//string is reversed beacuse ids appear to be stored backwards
		$this->id = strrev(strtoupper(trim($str)));
	}
	
	public function setPluginName($str) {
		$this->pluginName = $str;
	}
	
	public function setCompanyName($str) {
		$this->companyName = $str;
	}
	
	public function setContractLength($days) {
		$this->contractLength = intval($days);
	}
	
	public function setIncome($amount) {
		$this->income = intval($amount);
	}
	
	public function validate() {
		if(!ctype_alnum($this->id)) {
			throw new inputException("Plugin id must contain only alpha-numeric characters");
		}
		if(strlen($this->id) != self::IDLENGTH) {
			throw new inputException("Plugin id must be 3 alpha-numeric characters");
		}
		if(!strlen($this->pluginName)) {
			throw new inputException("Plugin name is not set or empty");
		}
		if(strlen($this->pluginName) > self::PLUGINNAMELIMIT) {
			throw new inputException("Plugin name is too long, length must be no more than " . self::PLUGINNAMELIMIT);
		}
		if(!strlen($this->companyName)) {
			throw new inputException("Company name is not set or empty");
		}
		if(strlen($this->companyName) > self::COMPANYNAMELIMIT) {
			throw new inputException("Company name is too long, length must be no more than " . self::COMPANYNAMELIMIT);
		}
		if($this->contractLength < self::CONTRACTMIN || $this->contractLength > self::CONTRACTMAX) {
			throw new inputException(sprintf(
				"Contract length must be between %d and %d", 
				self::CONTRACTMIN, 
				self::CONTRACTMAX
			));
		}
		if($this->income < self::INCOMEMIN || $this->income > self::INCOMEMAX) {
			throw new inputException(sprintf(
				"Income must be between %d and %d", 
				self::INCOMEMIN,
				self::INCOMEMAX
			));
		}
		if(is_dir($this->outputPath)) {
			throw new fileSystemException("Output path is a directory");
		}
		if(!file_exists($this->imagePath) || !is_readable($this->imagePath)) {
			throw new fileSystemException("Input file cannot be found or is not readable");
		}
	}
	
	public function outputFile() {
		$this->calculateSections();
		$this->processImage();
		$internalId = $this->id . 'B';
		
		//plugin header
		$this->writeString(self::MAGICNUM);
		$this->writeString(pack(self::LONG, 0x04)); //possibly a plugin type id, the movies have 0x07 set
		$this->writeString(self::ATTR);
		$this->writeString(pack(self::LONG, 0x80));
		$this->writeString(pack(self::LONG, $this->attrOffset)); //absolute offset to ATTR section
		$this->writeString(pack(self::LONG, $this->attrLength));
		$this->writeString(self::DESC);
		$this->writeString(pack(self::LONG, 0x80));
		$this->writeString(pack(self::LONG, $this->descOffset)); //absolute offset to DESC section
		$this->writeString(pack(self::LONG, $this->descLength));
		$this->writeString(self::ADDF);
		$this->writeString(pack(self::LONG, 0x80));
		$this->writeString(pack(self::LONG, $this->addfOffset)); //absolute offset to ADDF section
		$this->writeString(pack(self::LONG, $this->addfLength));
		//bitmap header doesn't contain a 4 character string like the others
		$this->writeString(pack(self::LONG, 0x00));	
		$this->writeString(pack(self::LONG, 0x80));
		$this->writeString(pack(self::LONG, $this->bitmapOffset));
		$this->writeString(pack(self::LONG, $this->bitmapLength));
		
		//attr section
		$this->writeString(pack(self::BYTE, 0x01)); //some kind of marker?
		$this->writeString($internalId);
		//start unknown
		$this->writeString(pack(self::LONG, 0x01));
		$this->writeString("****");
		$this->writeString(pack(self::LONG, 0x01));
		$this->writeString(pack(self::LONG, 0x00));
		$this->writeString(pack(self::LONG, 0x00));
		$this->writeString(pack(self::LONG, 0x00));
		//end unknown
		$this->writeString(pack(self::BYTE, strlen($this->pluginName)));
		$this->writeString($this->pluginName);
		$this->writeString(pack(self::NULLBYTE));
		
		//desc section - no idea what this does, all billboards seem to have the same values
		$this->writeString(pack(self::BYTE, 0x01));
		$this->writeString(self::ADPL);
		$this->writeString(pack(self::LONG, 0x03));
		
		//addf section - gameplay values
		$this->writeString(pack(self::BYTE, 0x01));
		$this->writeString(pack(self::BYTE, strlen($this->companyName)));
		$this->writeString($this->companyName);
		$this->writeString(pack(self::NULLBYTE));
		$this->writeString(pack(self::LONG, 0x00));
		$this->writeString(self::BOPI);
		$this->writeString(pack(self::LONG, 0x80));
		$this->writeString(pack(self::LONG, 0x01));
		$this->writeString(pack(self::LONG, $this->contractLength));
		$this->writeString(pack(self::LONG, $this->income));
		$this->writeString(pack(self::SHORT, 0x00));
		
		//bitmap section
		/*
			Note that the first bitmap header that is usually in Windows Bitmaps 
			is not present in the plugins. The bitmaps also contain a reduced
			copy of the game's palette in each file. The reduced palette ignores
			the colors that are cycled, but I'm not sure why it needs to be stored
			in each file. Please note that you will not be able to add colors by
			changing the color table.
		*/
		
		//dib header
		$this->writeString(pack(self::LONG, 0x28)); //dib header size
		$this->writeString(pack(self::LONG, self::WIDTH));
		$this->writeString(pack(self::LONG, self::HEIGHT));
		$this->writeString(pack(self::SHORT, 0x01)); //color planes
		$this->writeString(pack(self::SHORT, 0x08)); //bits per pixel
		$this->writeString(pack(self::LONG, 0x0)); //compression method
		$this->writeString(pack(self::LONG, self::WIDTH * self::HEIGHT)); //pixel array size
		$this->writeString(pack(self::LONG, 0x0B13)); //hor. res.
		$this->writeString(pack(self::LONG, 0x0B13)); //ver. res.
		$this->writeString(pack(self::LONG, 0x0)); //#colors, default calc works for this
		$this->writeString(pack(self::LONG, 0x0)); //important colors, not used for this
		
		//color table
		for($i=0;$i<palette::COLORS;$i++) {
			$data = $this->makeLongColor(
				palette::$data[$i][palette::R],
				palette::$data[$i][palette::G],
				palette::$data[$i][palette::B]
			);
			$this->writeString(pack(self::LONG, $data));
		}
		
		//write bitmap
		for($y=self::HEIGHT -1;$y>=0;$y--) {
			for($x=0;$x<self::WIDTH;$x++) {
				$this->writeString(pack(self::BYTE, imagecolorat($this->img, $x, $y)));
			}
		}
		$this->finalizeFile();
		
		if($this->imageCopyPath !== false) {
			imagepng($this->img, $this->imageCopyPath);
		}
		
		imagedestroy($this->img);
	}
	
	private function processImage() {
		//create image in memory
		$this->img = imagecreate(self::WIDTH, self::HEIGHT);
		//load palette,this should keep it in order
		for($i=0;$i<palette::COLORS;$i++) {
			imagecolorallocate($this->img, 
				palette::$data[$i][palette::R],
				palette::$data[$i][palette::G],
				palette::$data[$i][palette::B]
			);
		}
		//load in input file and copy into our memory image
		$in = @imagecreatefrompng($this->imagePath);
		if($in === false) {
			throw new imageException("Could not open input image as a PNG file");
		}
		imagecopy($this->img, $in, 0, 0, 0, 0, self::WIDTH, self::HEIGHT);
		imagedestroy($in);
	}
	
	private function makeLongColor($r, $g, $b) {
		$color  = $b;
		$color |= $g << 8;
		$color |= $r << 16;
		return $color;
	}
	
	private function calculateSections() {
		$this->attrLength = 0x1E + strlen($this->pluginName) + 1; //extra 1 is for null-termed string
		$this->descLength = 0x09;
		$this->addfLength = 0x1C + strlen($this->companyName) + 1; //extra 1 is for null-termed string
		$this->bitmapLength = self::BITMAPDIBSIZE + (palette::COLORS * 4) + (self::WIDTH * self::HEIGHT);
		
		$this->attrOffset = self::HEADERSIZE;
		$this->descOffset = $this->attrOffset + $this->attrLength;
		$this->addfOffset = $this->descOffset + $this->descLength;
		$this->bitmapOffset = $this->addfOffset + $this->addfLength;
	}
	
	//store string bytes in buffer variable
	private function writeString($str) {
		$this->dataBuffer .= $str;
	}
	
	//actually output the file
	private function finalizeFile() {
		$res = @file_put_contents($this->outputPath, $this->dataBuffer);
		if($res === false) {
			throw new fileSystemException("Could not output plugin file");
		}
	}
	
	public function __destruct() {
		if(is_resource($this->img)) {
			imagedestroy($this->img);
		}
	}
}